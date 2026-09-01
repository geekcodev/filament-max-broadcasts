# filament-max-broadcasts

Filament-плагин: **массовые рассылки** пользователям MAX-мессенджера внутри Filament-панели. Строится поверх
[`geekcodev/laravel-max-client`](https://github.com/geekcodev/laravel-max-client) (реестр чатов `max_chats`/`max_users`)
и [`geekcodev/max-php-client`](https://github.com/geekcodev/max-php-client) (API MAX).

Возможности:

- ресурс «Рассылки» (`BroadcastResource`): создание, список, просмотр, relation manager получателей;
- текст рассылки в формате HTML (RichEditor) с санитизацией под whitelist тегов MAX;
- тип «Новость» или «Акция»; для акций — настраиваемые кнопки-диплинки в мини-приложение;
- фото к рассылке (загрузка через `FileUpload`, лимит из конфига) и отложенная отправка (`scheduled_at`);
- сбор получателей — активные чаты из реестра `max_chats` (дедуп по `chat_id`, расширяемый резолвер);
- статусы `scheduled → running → completed/cancelled/failed` со счётчиками `total/delivered/failed`;
- очередь `SendBroadcastJob`: лок на рассылку, батчи, ретраи, отмена, событие `BroadcastCompleted`;
- действия «Повторить» / «Отправить сейчас» / «Отменить» / «Удалить», фильтры по статусу и типу;
- права настраиваются строками (`broadcasts.view` / `broadcasts.create` / `broadcasts.send` / `broadcasts.manage`
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
    'broadcasts.view', 'broadcasts.create', 'broadcasts.send', 'broadcasts.manage',
]);
```

## Конфигурация

```bash
php artisan vendor:publish --tag=filament-max-broadcasts-config   # config/filament-max-broadcasts.php
php artisan vendor:publish --tag=filament-max-broadcasts-lang     # lang/vendor/filament-max-broadcasts
php artisan vendor:publish --tag=filament-max-broadcasts-migrations # миграции
```

Ключевые параметры:

| Ключ                                                                      | По умолчанию                                       | Описание                                                                 |
|---------------------------------------------------------------------------|----------------------------------------------------|--------------------------------------------------------------------------|
| `permissions.*`                                                           | `broadcasts.view/create/send/manage`               | Права на доступ/создание/отправку/управление рассылками                  |
| `bot_username` / `promo_buttons`                                          | пусто (`''`), список `{text,startapp}`             | Имя бота и кнопки-диплинки для акций (`https://max.ru/<bot>?startapp=…`) |
| `queue.batch_size` / `lock_ttl_seconds` / `tries` / `timeout` / `backoff` | 25 / 600 / 3 / 3600 / `[60,300]`                   | Параметры очереди `SendBroadcastJob`                                     |
| `image.disk` / `directory` / `max_kb`                                     | `public` / `broadcasts` / 10240                    | Диск, каталог и лимит размера фото рассылки                              |
| `chats_model`                                                             | пакетный `Models\MaxChat`                          | Модель реестра чатов (переопределяйте подклассом)                        |
| `broadcast_model` / `recipient_model` / `user_model`                      | пакетные модели; `Illuminate\Foundation\Auth\User` | Переопределение моделей плагина                                          |
| `recipients.resolver`                                                     | пакетный `BroadcastRecipientsResolver`             | Класс выбора получателей для рассылки                                    |
| `ui.*`                                                                    | см. конфиг                                         | Иконка/лейблы/sort/slug навигации ресурса                                |

Настройки кнопок акций (тип «Акция»):

```php
'bot_username'   => 'my_service_bot',
'promo_buttons'  => [
    ['text' => 'Запись на сервис', 'startapp' => 'booking'],
    ['text' => 'Консультация',     'startapp' => 'consult'],
],
```

Если `bot_username` пуст или список кнопок пуст — кнопки не добавляются (рассылка «Новость»).

## Архитектура

- `Services\BroadcastService` — создание рассылки: резолв получателей, запись `Broadcast` + `BroadcastRecipient`,
  `dispatch()` очереди (с `delay()` при будущем расписании); `DispatchableBroadcast`-метод `dispatch()` для повторной
  отправки;
- `Services\BroadcastRecipientsResolver` — единственный источник списка получателей (активные чаты `max_chats`, дедуп по
  `chat_id`, сортировка по `last_activity_at`);
- `Services\BroadcastTextSanitizer` — санитизация HTML под whitelist тегов MAX + `toMaxHtml()` (разворачивание
  `<p>`/`<div>`/`<br>` в `\n`, иначе MAX не рендерит абзацы);
- `Services\BroadcastSender` — единая точка отправки в MAX: фото (`uploadMedia`), кнопки акций (`InlineKeyboard`),
  сообщение с `TextFormat::Html`;
- `Support\PromoButtons` — сборка кнопок-диплинков из конфига (`bot_username` + `promo_buttons`);
- `Jobs\SendBroadcastJob` — очередь: `Cache::lock("broadcast:{id}")`, статус `running`, батчи по `queue.batch_size`
  с проверкой отмены и обновлением счётчиков, по завершении — `completed` + `BroadcastCompleted`;
- `Events\BroadcastCompleted` — событие завершения рассылки;
- Модели `Models\Broadcast` (`max_broadcasts`) / `Models\BroadcastRecipient` (`max_broadcast_recipients`)
  с конфигурируемыми связями `creator()` → `user_model`, `maxChat()` → `chats_model`.

Прямые вызовы `ApiClient` из Filament-страниц запрещены — отправка только через `BroadcastSender`. Текст повторно
санитизируется перед каждой отправкой.

## Приём получателей

Получатели собираются серверно из реестра `max_chats` — из формы рассылки пользователь не может подставить произвольные
чат-идентификаторы. Чтобы расширить выбор (фильтры, исключения, целевые группы), замените класс резолвера через
`recipients.resolver` — он должен реализовывать тот же контракт `resolve(): Collection<int, MaxChat>`.

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