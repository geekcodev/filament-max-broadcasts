# filament-max-broadcasts

Filament-плагин: **массовые рассылки** пользователям MAX-мессенджера внутри Filament-панели. Строится поверх
[`geekcodev/laravel-max-client`](https://github.com/geekcodev/laravel-max-client) (реестр чатов `max_chats`/`max_users`)
и [`geekcodev/max-php-client`](https://github.com/geekcodev/max-php-client) (API MAX).

Возможности:

- ресурс «Рассылки» (`BroadcastResource`): создание, список, просмотр, relation manager получателей;
- ресурс «Сегменты» (`BroadcastSegmentResource`): именованные группы чатов для целевого выбора получателей;
- выбор получателей при создании — несколько сегментов и/или конкретные чаты с серверным поиском по `chat_id`
  (снимок `recipient_chat_ids`, повторы уходят тем же людям);
- текст рассылки в формате HTML (RichEditor) с санитизацией под whitelist тегов MAX;
- тип «Новость» или «Акция» (реестр типов в конфиге, добавляются свои); для типов с настроенными кнопками —
  кнопки-диплинки в мини-приложение;
- медиа-вложения к рассылке — несколько картинок, видео и файлов (загрузка через `FileUpload`, типы и лимит из конфига)
  и отложенная отправка (`scheduled_at`);
- сбор получателей — активные чаты из реестра `max_chats` (дедуп по `chat_id`, расширяемый резолвер);
- opt-in согласие на рассылку: действие «Запросить согласие» рассылает callback-кнопки и складывает согласившихся в
  сегмент «Новости и акции»;
- статусы `scheduled → running → completed/cancelled/failed` со счётчиками `total/delivered/failed`;
- очередь `SendBroadcastJob`: лок на рассылку, батчи, ретраи, отмена, событие `BroadcastCompleted`;
- действия «Повторить» / «Отправить сейчас» / «Отменить» / «Удалить», фильтры по статусу и типу;
- права настраиваются строками (`broadcasts.view` / `broadcasts.create` / `broadcasts.manage`
  по умолчанию) — совместимо со spatie/laravel-permission и Gate.

## Требования

- PHP ^8.4, Laravel ^13.0
- Filament ^5.0 (панель v5), Livewire ^4.1
- `geekcodev/laravel-max-client` ^1.1.0 + `geekcodev/max-php-client` ^1.0.9
- Опубликованные миграции laravel-max-client (`max_users`, `max_chats`)
- Работающая очередь (для фактической отправки)

## Установка

```bash
composer require geekcodev/filament-max-broadcasts
php artisan migrate    # миграции max_broadcasts / max_broadcast_recipients загружаются из пакета автоматически
```

Подключение к панели:

```php
use GeekCo\FilamentMaxBroadcasts\FilamentMaxBroadcastsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentMaxBroadcastsPlugin::make());
}
```

Права (пример со spatie/laravel-permission):

```php
Role::findByName('admin')->givePermissionTo([
    'broadcasts.view', 'broadcasts.create', 'broadcasts.manage',
]);
```

## Конфигурация

```bash
php artisan vendor:publish --tag=filament-max-broadcasts-config   # config/filament-max-broadcasts.php
php artisan vendor:publish --tag=filament-max-broadcasts-lang     # lang/vendor/filament-max-broadcasts
php artisan vendor:publish --tag=filament-max-broadcasts-migrations # миграции
```

Ключевые параметры:

| Ключ                                                                      | По умолчанию                                       | Описание                                                                            |
|---------------------------------------------------------------------------|----------------------------------------------------|-------------------------------------------------------------------------------------|
| `permissions.*`                                                           | `broadcasts.view/create/manage`                    | Права на доступ/создание/управление рассылками                                      |
| `types`                                                                   | пакетные `News` / `Promo` enums                    | Реестр типов рассылок: `token` → класс, реализующий `BroadcastTypeContract`         |
| `bot_username` / `buttons.per_type`                                       | пусто (`''`) / `[]`                                | Имя бота и кнопки-диплинки по типу по умолчанию (`https://max.ru/<bot>?startapp=…`) |
| `queue.batch_size` / `lock_ttl_seconds` / `tries` / `timeout` / `backoff` | 25 / 600 / 3 / 3600 / `[60,300]`                   | Параметры очереди `SendBroadcastJob`                                                |
| `image.disk` / `directory` / `max_kb` / `accepted_mime_types`             | `public` / `broadcasts` / 51200 (КБ, ~50 МБ) / …   | Диск, каталог, лимит размера и допустимые типы медиавложений рассылки               |
| `chats_model`                                                             | пакетный `Models\MaxChat`                          | Модель реестра чатов (переопределяйте подклассом)                                   |
| `broadcast_model` / `recipient_model` / `segment_model` / `user_model`    | пакетные модели; `Illuminate\Foundation\Auth\User` | Переопределение моделей плагина                                                     |
| `consent.*`                                                               | сегмент «Новости и акции», текст запроса, кнопки   | Opt-in согласие: модель, имя сегмента, кнопки, тексты подтверждения opt-in/opt-out  |
| `recipients.resolver`                                                     | пакетный `BroadcastRecipientsResolver`             | Класс выбора получателей для рассылки                                               |
| `ui.*`                                                                    | см. конфиг                                         | Иконка/лейблы/sort/slug навигации ресурса                                           |

Настройки кнопок-диплинков по умолчанию (по типу рассылки):

```php
'bot_username' => 'my_service_bot',
'buttons' => [
    'per_type' => [
        'promo' => [
            ['text' => 'Запись на сервис', 'startapp' => 'booking'],
            ['text' => 'Консультация',     'startapp' => 'consult'],
        ],
    ],
],
```

Кнопок нет для типа, если `bot_username` пуст, для него ничего не настроено в `buttons.per_type`, либо тип переопределил
`buttonRows()` (по умолчанию `'news'` идёт без кнопок). Свои типы добавляются в реестр `types` классом, реализующим
`Contracts\BroadcastTypeContract` (простейший — трейт `Support\BroadcastTypeDefaults` + регистрация в конфиге).
Поведение типа (подписи, кнопки, цвет badge) живёт в самом типе.

## Архитектура

- `Services\BroadcastService` — создание рассылки: резолв получателей (явные чаты → сегменты → все активные), запись
  `Broadcast` + `BroadcastRecipient`, привязка сегментов, `dispatch()` очереди (с `delay()` при будущем расписании);
- `Services\BroadcastRecipientsResolver` — единственный источник списка получателей (активные чаты `max_chats`, дедуп по
  `chat_id`, сортировка по `last_activity_at`);
- `Models\BroadcastSegment` + `Services\ConsentService` — именованные сегменты чатов; сегмент «Новости и акции» —
  источник согласившихся на рассылку (факты `opt_in`/`opt_out` в `max_broadcast_consents`);
- `Services\ConsentRequestService` + `Jobs\SendConsentRequestsJob` — действие «Запросить согласие»: кандидаты — активные
  чаты без ответа, рассылка фиксированного сообщения с callback-кнопками;
- `Listeners\HandleConsentCallback` — приём ответов (событие `MaxUpdateReceived`), запись факта и ответ `sendAnswer`;
- `Services\BroadcastTextSanitizer` — санитизация HTML под whitelist тегов MAX + `toMaxHtml()` (разворачивание
  `<p>`/`<div>`/`<br>` в `\n`, иначе MAX не рендерит абзацы);
- `Services\BroadcastSender` — единая точка отправки в MAX: медиавложения (картинки/видео/файлы через `uploadMedia`),
  кнопки-диплинки (`InlineKeyboard`), сообщение с `TextFormat::Html`;
- `Support\BroadcastTypes` — реестр типов из конфига (`types`, token → класс контракта) для форм/фильтров/колонок:
  `options()`, `instance()`, `label()`, `badgeColor()`;
- `Contracts\BroadcastTypeContract` + `Support\BroadcastTypeDefaults` — типы как поведение: каждый тип — свой
  backed-enum, подписи/кнопки/цвет определяет сам тип (дефолты — из трейта, кнопки по умолчанию из
  `bot_username` + `buttons.per_type`);
- `Jobs\SendBroadcastJob` — очередь: `Cache::lock("broadcast:{id}")`, статус `running`, батчи по `queue.batch_size`
  с проверкой отмены и обновлением счётчиков, по завершении — `completed` + `BroadcastCompleted`;
- `Events\BroadcastCompleted` — событие завершения рассылки;
- Модели `Models\Broadcast` (`max_broadcasts`) / `Models\BroadcastRecipient` (`max_broadcast_recipients`)
  с конфигурируемыми связями `creator()` → `user_model`, `maxChat()` → `chats_model`.

Прямые вызовы `ApiClient` из Filament-страниц запрещены — отправка только через `BroadcastSender`. Текст повторно
санитизируется перед каждой отправкой.

## Приём получателей

Получатели собираются серверно из реестра `max_chats` — из формы рассылки пользователь не может подставить произвольные
чат-идентификаторы. Выбор при создании: несколько сегментов (`BroadcastSegment`, union их `chat_ids`) и/или ручная
корректировка поиском активных чатов (клиент подгружает только найденные); фактический список получателей сохраняется
снимком в `recipient_chat_ids`
(пусто — всем активным). Действия «Повторить» отправляют рассылку тому же снимку, поэтому повтор уходит тем же людям,
даже если состав сегментов изменился. Чтобы расширить базовый источник чатов (фильтры, исключения, целевые группы),
замените класс резолвера через `recipients.resolver` — он должен реализовывать контракт
`resolve(): Collection<int, MaxChat>`.

## Согласие на рассылку (opt-in)

На списке рассылок есть действие «Запросить согласие» (`permissions.create`): оно ставит в очередь рассылку
фиксированного сообщения с callback-кнопками «Согласен»/«Не согласен» всем активным чатам, ещё не ответившим. Ответы
принимаются слушателем на webhook (`HandleConsentCallback`), факт записывается в `max_broadcast_consents`, а
согласившиеся попадают в сегмент «Новости и акции» (`consent.segment_name`) — по нему можно делать целевые рассылки.
Факт получения запроса не фиксируется: проигнорировавшие получат запрос снова при следующем запуске, ответившие —
никогда.

## Тестирование и разработка

PHP/Composer на хосте не требуются — всё через Docker (образ PHP 8.4, Orchestra Testbench):

```bash
docker compose up -d --build   # контейнер app (PHP 8.4)
docker compose run --rm app composer install
docker compose exec app composer test       # PHPUnit (SQLite in-memory)
docker compose exec app composer analyse    # PHPStan level max (Larastan + baseline)
docker compose exec app composer lint       # PHP-CS-Fixer (--dry-run)
docker compose exec app composer format     # PHP-CS-Fixer (исправить)
docker compose exec app composer audit      # composer audit
```