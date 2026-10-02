# План: сбор согласия получателей через callback-кнопки (opt-in consent)

> Статус: **уточнён по ответам пользователя** → идёт реализация.
> Механика ложится на готовую инфраструктуру laravel-max-client (вебхук-доставка `MaxUpdateReceived`)
> и уже реализованные сегменты (`BroadcastSegment`). Не изобретаем сигнатуры MAX — только те, что
> подтверждены в `vendor/geekcodev/max-php-client` и `vendor/geekcodev/laravel-max-client` (см. конец файла).

---

## 1. Цель

Дать оператору возможность собрать **явное согласие (opt-in)** получателей на рассылку и использовать
согласившихся как аудиторию.

### Уточнённые требования (ответы пользователя)

- Отправка запроса — **НЕ обычная рассылка**, а **кнопка «Запросить согласие»** на странице списка
  рассылок (`ListBroadcasts`, header-действие).
- При нажатии рассылается **фиксированное сообщение** «Согласны ли вы получать наши новости и акции?»
  с кнопками «Согласен» / «Не согласен» — **всем ещё не ответившим**.
- **Факт получения НЕ фиксируется.** Кто проигнорировал — при повторном запуске снова получит запрос.
- **Фиксируется только ответ**: `opt_in` или `opt_out`. Кто ответил (в любую сторону) — больше НЕ получает
  запрос при повторных запусках.
- Размещение кнопки: header-действие на `ListBroadcasts`.

### Итоговая модель

- Получатели запроса = активные чаты `max_chats` **без записи** в `max_broadcast_consents`
  (нет ни `opt_in`, ни `opt_out`).
- Ответ записывается в `max_broadcast_consents` (upsert) и **chat_id согласившихся складывается в сегмент
  согласий** (`BroadcastSegment` по имени `consent.segment_name`). Таргетные рассылки идут в этот сегмент
  — механизм сегментов уже реализован.
- Отправка — в **очередь** (`SendConsentRequestsJob`), чтобы не блокировать веб-запрос кнопки.

---

## 2. Как MAX обрабатывает нажатие кнопки (подтверждённые сигнатуры)

- Кнопка: `ButtonType::Callback` с `payload` (строка) — `GeekCo\MaxPhpClient\Dto\InlineKeyboardButton`.
- Клавиатура: `InlineKeyboardButtonRow(buttons: list<InlineKeyboardButton>)` → `AttachmentRequest`
  `AttachmentType::InlineKeyboard` (уже умеет `BroadcastSender`).
- Вебхук: `Update` с `updateType = UpdateType::MessageCallback`, `callback: Callback{callbackId, payload, message}`.
  `chat_id` резолвится в `Update::$chatId` (фолбэк из `callback.message.recipient.chat_id`), `user` — `Update::$user`.
- Ответ на callback (обязателен, иначе MAX сочтёт необработанным):
  `ApiClient::sendAnswer($callbackId, ?NewMessageBody, ?notification)` — хотя бы `notification` обязателен
  (иначе `InvalidArgumentException`). Есть deprecated-нюанс из ядра: `sendAnswer()` без `message` шлёт `{}`.
- Доставка до нас уже готова: `MaxUpdateReceived` (событие с `Update`), обработка асинхронна через
  `HandleMaxUpdateJob` (очередь) → не блокирует 30-секундное окно вебхука, rate limit учтён ядром.

---

## 3. Архитектура и изменения

### 3.1. Конфиг (`config/filament-max-broadcasts.php`)

Новый блок `consent`:

```php
'consent' => [
    'consent_model'        => GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent::class,
    'segment_name'         => 'Новости и акции',   // имя автосоздаваемого сегмента согласий (только его затрагивает согласие)
    'payload_prefix'       => 'consent',
    'request_message'      => 'Согласны ли вы получать наши новости и акции?',
    'button_text_opt_in'   => 'Согласен',
    'button_text_opt_out'  => 'Не согласен',
    'answer_notification'  => 'Спасибо! Ваш ответ учтён.', // текст ответа на callback
],
```

Важно (AGENTS п.9): `.env.example` синхронизируем только если добавляем `FILAMENT_MAX_BROADCASTS_*` env.
В этом блоке всё через конфиг без env — `.env.example` не трогаем.

### 3.2. Права

Кнопка «Запросить согласие» — требует права `broadcasts.create` (создание/отправка) — используем
`permissions.create`. Обработка нажатий кнопки в вебхуке — без прав (сервер-к-серверу).

### 3.3. Таблица `max_broadcast_consents` + миграция

```sql
max_broadcast_consents
  id          bigint PK auto
  segment_id  FK -> max_broadcast_segments (nullOnDelete)  -- сегмент, куда добавляем/откуда убираем
  chat_id     bigint unsigned
  user_id     bigint unsigned nullable                     -- MAX user_id из Update (может быть null для каналов)
  action      string enum('opt_in','opt_out')              -- последнее действие
  source      string default 'callback'                    -- источник (пока один)
  created_at / updated_at
  unique(segment_id, chat_id)
  index(segment_id, action)
```

Новая миграция `0001_01_01_000005_create_max_broadcast_consents_table.php` в `database/migrations/`.
(Реализовано в прошлой итерации.)

### 3.4. Модель `BroadcastConsent`

(Реализовано в прошлой итерации.) `src/Models/BroadcastConsent.php`: guarded/`#[\Fillable]`, casts
`action` → `Enums/BroadcastConsentAction.php`, relation `segment()`. Default `source='callback'`.

### 3.5. Callback-кнопки — более НЕ тип «Опрос согласия» как рассылка

Уточнение: отправка запроса согласия — не обычная рассылка, поэтому **убираем `consent` из реестра `types`**
(оператор не должен создавать подобную рассылку вручную в форме). Кнопки согласия строим через
**`ConsentRequestService`**/`SendConsentRequestsJob` напрямую, через `BroadcastSender`.

Для переиспользования кнопок оставляем enum `ConsentPoll` (implements `BroadcastTypeContract`), но **не
регистрируем** его в `types` — он служит источником фиксированных callback-кнопок для джобы.

### 3.6. `ConsentService` — расширение

`src/Services/ConsentService.php` (реализовано; добавляем метод):

- `optIn(int $chatId, ?int $userId): void` — upsert `opt_in`, + chat_id в сегмент согласий.
- `optOut(int $chatId, ?int $userId): void` — upsert `opt_out`, − chat_id из сегмента.
- `resolveConsentSegment(): BroadcastSegment` — firstOrCreate по `consent.segment_name`.
- `consentChatIds(): array` — chat_id согласившихся.
- `answeredChatIds(): array` — chat_id ВСЕХ, кто ответил (opt_in или opt_out). Источник дедупа для рассылки.

### 3.7. `ConsentRequestService` (новый)

`src/Services/ConsentRequestService.php`:
- `eligibleChatIds(): list<int>` — активные чаты (резолвер) минус `answeredChatIds()`.
- `sendRequest(?Model $creator = null): int` — считает `eligibleChatIds()`, если не пуст —
  `SendConsentRequestsJob::dispatch()` (в очередь), возвращает число получателей.
- (ответственность СРП: сервис считает и диспатчит; сама отправка — в джобе.)

### 3.8. `SendConsentRequestsJob` (новый)

`src/Jobs/SendConsentRequestsJob.php`:
- `Cache::lock('consent:send')` — защита от параллельных запусков (двойная рассылка за время,
  пока никто не ответил).
- Класс пере-резолвит `eligibleChatIds()` **на момент выполнения** (актуальность).
- Для каждого чата — `BroadcastSender->send(new Recipient(chatId, userId), toMaxHtml(request_message),
  $media: [], $type: ConsentPoll::ConsentPoll)` — кнопки согласия добавляются через `buttonRows()` типа.
- Батчи (по `queue.batch_size`), ретраи (`queue.tries`/`backoff`) — по шаблону `SendBroadcastJob`.
- Исключения на отдельного получателя — логируются, не валят всю рассылку.

### 3.9. Header-действие на `ListBroadcasts`

`src/Resources/Pages/ListBroadcasts.php` — `getHeaderActions()` добавляет `Action::make('request_consent')`:
- label «Запросить согласие», иконка, `requiresConfirmation()` с описанием;
- `authorize(config permissions.create)`;
- действие: `$count = app(ConsentRequestService::class)->sendRequest($user);`
  Notification «Запрос согласия поставлен в очередь» (+ число получателей), или «Нет получателей».

### 3.10. `BroadcastSender`

Изменений не требуется — `send()` уже прикладывает `buttonRows()` типа как inline-клавиатуру.
`ConsentPoll::buttonRows()` возвращает callback-кнопки.

---

## 4. Открытые вопросы (решения по умолчанию — в квадратных скобках)

1. **Повторное нажатие «Согласен»**: upsert одной актуальной записи на пару segment+chat — [да].
2. **Общий vs раздельный notification при ответе** — [единый `consent.answer_notification`].
3. **Сегмент согласий — фиксированный** (один на бота) — [да, payload `consent:<action>`].
4. **Кто «ещё не получал»** — [те, у кого НЕТ записи opt_in/opt_out; игнорировавшие снова получают —
   это требование пользователя].
5. **Хранить ли факт получения** — [нет, только ответы — требование пользователя].

---

## 5. Тесты

- (реализовано) `BroadcastConsentTest`, `BroadcastConsentActionTest`, `ConsentServiceTest`, `HandleConsentCallbackTest`.
- `ConsentPollTypeTest` — обновить: тип НЕ зарегистрирован в `types` (не выбирается в форме), но кнопки callback.
- `ConsentRequestServiceTest` (новый): `eligibleChatIds()` исключает ответивших; `sendRequest()` диспатчит джоб
  и возвращает число; при пустом списке — не диспатчит и возвращает 0.
- `SendConsentRequestsJobTest` (новый): с мок `BroadcastSender`, резолвером; шлёт только неответившим,
  батчинг, lock.
- `ListBroadcastsTest` (feature, новый): кнопка «Запросить согласие» видна, диспатчит джоб и шлёт Notification.

Gate: `composer lint`, `composer analyse` (PHPStan level max), `composer test`, `composer audit`.

---

## 6. Этапы реализации (порядок)

1. (готово) миграция + модель + enum + конфиг `consent` + `ConsentService` + слушатель + lang.
2. Убрать `consent` из `types` конфига (оставить enum `ConsentPoll` как источник кнопок).
3. `ConsentService::answeredChatIds()`.
4. `ConsentRequestService`.
5. `SendConsentRequestsJob`.
6. Header-действие на `ListBroadcasts` + lang-ключи кнопки.
7. Обновить/добавить тесты + журнал + session-файл.
8. Gate.

---

## 7. Источник истины (подтверждено по коду)

- `GeekCo\MaxPhpClient\Enum\ButtonType::Callback`, `UpdateType::MessageCallback`.
- `GeekCo\MaxPhpClient\Dto\InlineKeyboardButton{Callback, text, payload, url=null}`.
- `GeekCo\MaxPhpClient\Dto\InlineKeyboardButtonRow(buttons)`.
- `GeekCo\MaxPhpClient\Dto\Callback{callbackId, payload, message}`.
- `GeekCo\MaxPhpClient\Dto\Update{updateType, chatId, user, callback}` (chat_id уже фолбечит из message/callback).
- `GeekCo\MaxPhpClient\ApiClient::sendAnswer(string $callbackId, ?NewMessageBody, ?string $notification): SuccessResponse`
  — требует `message` или `notification` (иначе `InvalidArgumentException`).
- `GeekCo\LaravelMaxClient\Webhook\MaxUpdateReceived(Update $update)` — событие доставки апдейтов в очередь.
- `BroadcastSender::send()` уже прикладывает `InlineKeyboardButtonRow[]` как InlineKeyboard attach.
