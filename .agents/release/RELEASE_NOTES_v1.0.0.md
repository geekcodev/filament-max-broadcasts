# v1.0.0

Filament v5 плагин для массовых рассылок пользователям MAX-мессенджера.

## Что внутри

Ресурс «Рассылки» (`/admin/broadcasts`): создание, список, просмотр, relation manager получателей.

Рассылка создаётся с текстом в HTML (RichEditor), типом из реестра и отложенным временем отправки. К рассылке
прикрепляются медиавложения — несколько картинок, видео и файлов (загрузка через `FileUpload`, типы и лимит из конфига).
Текст санитизируется по белому списку тегов MAX и перед отправкой разворачивается в формат MAX (абзацы, переносы строк).
Типы рассылок — это поведение (подписи, цвет badge, кнопки-диплинки): реестр `types` в конфиге, по умолчанию пакетные
`News`/`Promo`. Для типов с настроенными кнопками к сообщению добавляется `InlineKeyboard`-диплинк в мини-приложение.

Получатели собираются из активных чатов реестра `max_chats` (дедуп по `chat_id`, расширяемый резолвер). Отправка идёт
через очередь `SendBroadcastJob`: лок на рассылку, батчи по 25, ретраи, поддержка отмены, счётчики доставленных/упавших.
По завершении рассылка получает статус `completed` и генерируется событие `BroadcastCompleted`. Отправка в MAX — только
через `Services\BroadcastSender` (медиавложения через `uploadMedia`, формат HTML), прямые вызовы ApiClient из страниц
запрещены.

## Требования

- PHP ^8.4
- Laravel 13 / Filament 5
- `geekcodev/laravel-max-client` ^1.1.0 с опубликованными миграциями (`max_users`, `max_chats`)
- `geekcodev/max-php-client` ^1.0.9
- Работающая очередь

## Установка

```bash
composer require geekcodev/filament-max-broadcasts
php artisan migrate   # миграции max_broadcasts / max_broadcast_recipients / max_broadcast_attachments загружаются из пакета
```

Подключить плагин в `AdminPanelProvider`:

```php
use GeekCo\FilamentMaxBroadcasts\FilamentMaxBroadcastsPlugin;

$panel->plugin(FilamentMaxBroadcastsPlugin::make());
```

Дать пользователю/роли права (пример):

```php
$user->givePermissionTo(['broadcasts.view', 'broadcasts.create', 'broadcasts.manage']);
```

## Конфигурация

Все опции в `config/filament-max-broadcasts.php`:

| Ключ                                                                    | Что делает                                         | По умолчанию                           |
|-------------------------------------------------------------------------|----------------------------------------------------|----------------------------------------|
| `permissions.view` / `create` / `manage`                                | Доступ / создание / управление рассылками          | `broadcasts.*`                         |
| `types`                                                                 | Реестр типов рассылок (`token` → класс интерфейса) | пакетные `News` / `Promo`              |
| `bot_username` / `buttons.per_type`                                     | Бот и кнопки-диплинки по типу                      | пусто (кнопок нет)                     |
| `queue.batch_size` / `lock_ttl_seconds` / `tries` / `timeout`/`backoff` | Параметры очереди `SendBroadcastJob`               | 25 / 600 / 3 / 3600 / `[60,300]`       |
| `image.disk` / `directory` / `max_kb` / `accepted_mime_types`           | Диск, каталог, лимит (50 МБ) и типы медиавложений  | `public` / `broadcasts` / 51200 (КБ)   |
| `broadcast_model` / `recipient_model` / `chats_model` / `user_model`    | Переопределение моделей плагина                    | пакетные / `Auth\User`                 |
| `recipients.resolver`                                                   | Класс выбора получателей                           | пакетный `BroadcastRecipientsResolver` |
| `ui.*`                                                                  | Иконка / лейблы / sort / slug ресурса              | `heroicon-o-megaphone` и др.           |

## Тесты

90 тестов (247 assertions). PHPStan level max (Larastan, baseline пустой — код и тесты полностью типизированы), PSR-12,
`declare(strict_types=1)`. Покрытие: строки 90%. GitHub Actions CI (`.github/workflows/ci.yml`): lint (php-cs-fixer),
PHPStan, PHPUnit, `composer audit` — на `push`/`pull_request` в `main`.

---

[Laravel](https://laravel.com) · [Filament](https://filamentphp.com) · [laravel-max-client](https://github.com/geekcodev/laravel-max-client) · [max-php-client](https://github.com/geekcodev/max-php-client)