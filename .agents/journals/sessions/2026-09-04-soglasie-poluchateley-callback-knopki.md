---
tags: [ consent, callback, migration, lang ]
date: 2026-09-04
---

# сбор согласия получателей через callback-кнопки (opt-in consent)

Дата: 2026-09-04. Продолжение после реализованных сегментов получателей.

## Контекст и цель

Пользователь спросил, можно ли добавить opt-in согласие получателей. Я выяснил по коду laravel-max-client /
max-php-client, что инфраструктура для этого уже есть: MAX поддерживает callback-кнопки
(`ButtonType::Callback`, `UpdateType::MessageCallback`), laravel-max-client доставляет апдейты в очередь через
событие `MaxUpdateReceived`, а ответить на нажатие можно `ApiClient::sendAnswer()`. План оформлен в
`.ai/progress/PLAN-consent-callback.md` и утверждён: сформировать план в файл и реализовать.

## Что сделано

- **Таблица `max_broadcast_consents`** (миграция `0001_01_01_000006_create_max_broadcast_consents_table.php`):
  `segment_id` FK→`max_broadcast_segments` (nullOnDelete), `chat_id`, `user_id` (nullable), `action`,
  `source` default `'callback'`, `created_at/updated_at`, unique `(segment_id, chat_id)`, index
  `(segment_id, action)`.
- **Модель `BroadcastConsent`** (`src/Models/BroadcastConsent.php`): `#[\Fillable]`, casts
  (`action→BroadcastConsentAction`, `chat_id`/`user_id` integer, `source` string), default-атрибут
  `source='callback'`, relation `segment()`.
- **Enum `BroadcastConsentAction`** (`src/Enums/BroadcastConsentAction.php`): `OptIn='opt_in'`,
  `OptOut='opt_out'`, `label()` из lang.
- **Тип «Опрос согласия» `ConsentPoll`** (`src/Enums/BroadcastTypes/ConsentPoll.php`): backed-enum
  `consent`, badage `success`, `buttonRows()` → одна строка из двух callback-кнопок
  `consent:opt_in` / `consent:opt_out` (без `url`, тексты из конфига). Зарегистрирован в `types`.
- **`ConsentService`** (`src/Services/ConsentService.php`): `optIn()/optOut()` — upsert факта и мутация
  `chat_ids` сегмента согласий; `resolveConsentSegment()` — firstOrCreate по `consent.segment_name`;
  `consentChatIds()`. Модель через `consent.consent_model`.
- **Слушатель `HandleConsentCallback`** (`src/Listeners/HandleConsentCallback.php`): подписан на
  `MaxUpdateReceived`; фильтрует `MessageCallback` + payload `consent:*`; `match` opt_in/opt_out → сервис;
  ответ `sendAnswer($callbackId, notification: ...)`; `MaxApiException` логируется без чувствительных данных.
- **Провайдер**: singleton `ConsentService` + `Dispatcher::listen(MaxUpdateReceived, HandleConsentCallback)` в `boot()`.
- **Конфиг**: блок `consent`, регистрация типа `consent`.
- **lang ru/en**: `type.consent`, `consent.action.opt_in/opt_out`.
- **Тесты** (5 новых файлов, +19 к прошлой сессии до 121 теста / 352 assertions): модель, enum-actions,
  тип ConsentPoll (label/badge/кнопки, зависимость от конфига), ConsentService (opt-in/out/идемпотентность/
  резолв сегмента), HandleConsentCallback (игнор не-callback/чужих/неизвестных, opt-in и opt-out с проверкой
  HTTP-запроса `/answers` и тела notification).

## Решения и отклонения

- **Мок ApiClient недоступен** (класс final) — слушатель тестируется через реальный
  `ApiClient::create` + `MockHttpClient` (паттерн уже есть в `BroadcastSenderTest`). Ответы на callback
  проверяются по HTTP-запросу.
- **Одна актуальная запись на пару (segment, chat)** — upsert, не история нажатий. Полноценный аудит лог
  каждого нажатия решили пока не строить.
- **Единый `answer_notification`** для opt_in и opt_out.
- **Фиксированный сегмент согласий** — payload `consent:<action>` без segment_id; формат оставляет место для
  расширения до `consent:<action>:<segment_id>`.
- **Без отдельного ресурса «Согласия»** — факты читаются через сегмент согласий и таблицу.
- **Без кнопки «Запустить опрос согласия» в UI** — опрос создаётся как обычная рассылка типа consent
  (тип добавляет кнопки автоматически в `BroadcastSender`).
- **`.env.example` не менялся** — новых `FILAMENT_MAX_BROADCASTS_*` env нет, всё через конфиг.
- PHPStan: для single-case enum `values` проверяем через helper с `@return list<string>` (иначе
  `alreadyNarrowedType`); `$data` в `makeUpdate` аннотирован `array<string, mixed>`.

## Gate

- lint 0, format 0
- analyse (PHPStan level max): 0 ошибок
- test: OK (121 tests, 352 assertions)
- audit: 0 критичных

## Дальше (если понадобится)

- Расширение payload до мульти-сегментов (`consent:<action>:<segment_id>`).
- Аудит-лог нажатий (каждая строка) вместо одной актуальной записи.

---

# Доводка по уточнению пользователя: запрос согласия — кнопка, не тип рассылки

## Что изменилось

Пользователь уточнил целевую механику (вопрос-уточнение, 3 ответа):
1. Факт получения запроса НЕ фиксировать — проигнорировавшие получат запрос снова при следующем запуске.
2. Нажавший «Не согласен» (и согласившийся) повторно запрос не получает.
3. Размещение — header-действие на таблице рассылок (`ListBroadcasts`).

Итог: раньше запрос согласия был **типом рассылки** (создаётся как обычный Broadcast, тип добавляет кнопки).
Теперь это **отдельная сущность «запрос»**, не создающая Broadcast:
- `Services/ConsentRequestService` — `eligibleChats()` (активные чаты без записей согласий),
  `eligibleChatIds()`, `sendRequest()` (диспатч + возврат числа получателей, 0 = некого опрашивать).
- `Jobs/SendConsentRequestsJob` — `Cache::lock('consent:send-request')`, пере-резолв кандидатов на момент
  выполнения, батчи `queue.batch_size`, отправка фиксированного сообщения с callback-кнопками (через
  `BroadcastSender` + `ConsentPoll`-тип), per-recipient try/catch с логом.
- `ListBroadcasts::getHeaderActions()` — `Action('request_consent')`: modal-подтверждение, право
  `permissions.create`, Notification с числом получателей или «нет получателей».
- `ConsentService::answeredChatIds()` — все chat_id из `max_broadcast_consents` (и opt_in, и opt_out).
- Из `types` конфига убран `consent`; enum `ConsentPoll` остался (не в реестре) как источник кнопок для джобы.
- Конфиг: добавлен `consent.request_message` («Согласны ли вы получать наши новости и акции?»).
- lang ru/en: `actions.request_consent*`, `notifications.consent_request_started`/`consent_no_recipients`.

## Уточнение про целевой сегмент (второй вопрос пользователя)

- Согласие записывается **именно в сегмент «Новости и акции»**: `consent.segment_name` дефолт изменён с
  «Согласие на рассылку» → «Новости и акции» (и в конфиге, и в фолбэке `ConsentService::resolveConsentSegment()`).
- Дедуп для кнопки «Запросить согласие» тоже скоуплен на этот сегмент: `answeredChatIds()` фильтрует записи
  `max_broadcast_consents` по `segment_id` сегмента «Новости и акции». Факт согласия/несогласия в **другом**
  сегменте в дедупе не участвует и повторно отправит запрос. Для остальных сегментов механика согласия не
  применяется вовсе.
- Тест: `testAnsweredChatIdsIgnoresRecordsInOtherSegments` — запись в сегменте «Другая рассылка» не считается
  ответом.
- Комментарий в конфиге уточнён: кнопка и записи привязаны только к сегменту «Новости и акции».

## Первичный сегмент в миграции (третье уточнение пользователя)

Пользователь изложил полное видение схемы: типы рассылок расширяемы, сегменты — группы с произвольными названиями,
первичный сегмент «Новости и акции» должен создаваться в миграции, кнопка согласия шлёт всем, кроме нажавших,
при создании рассылки выбираются сегменты и/или конкретные получатели.

Сверил с реализацией — совпадает всё, кроме сида:
- **Было:** сегмент согласий автосоздаётся лениво (`ConsentService::resolveConsentSegment()` firstOrCreate).
- **Стало:** `0001_01_01_000004_create_max_broadcast_segments_table.php` в `up()` после создания таблицы вставляет
  первичный сегмент без участников (`chat_ids='[]'`, created_by=null, имя из конфига `consent.segment_name`).

Побочный эффект: `BroadcastSegmentResourceTest::testCreateSegmentThroughForm` брал `firstOrFail()` — начал получать
сид; выборку поправил на `where('name', 'VIP clients')`.

**Открытый вопрос:** при создании рассылки сейчас выбирается один сегмент (Select) + конкретные получатели
(CheckboxList) с ручной корректировкой. Мультивыбор нескольких сегментов одновременно не поддержан — если нужен,
это отдельное изменение (схема `segment_id` + форма).

## Решения

- Не создаём `Broadcast`/`BroadcastRecipient` для запроса согласия (нет отслеживания «получено»).
- Право для кнопки — `broadcasts.create` (как у создания рассылки).
- PHPStan level max: `MaxChat` (vendor, без `@property`) — всегда через `getAttribute()` + локальный `@var`;
  `list<int>` через `array_values(...)` (phpstan не выводит `values()->all()` как list); Filament
  `assertActionVisible/Hidden` — `@phpstan-ignore method.notFound` (прецедент `assertCanSeeTableRecords`).

## Gate (после доводки)

- lint 0, format 0
- analyse (PHPStan level max): 0 ошибок
- test: OK (138 tests, 384 assertions; было 121/352)
- audit: 0 критичных