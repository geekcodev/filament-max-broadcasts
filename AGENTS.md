# AGENTS.md

> Проектный контекст и рабочие правила для разработчиков и ИИ-агентов (включая opencode).
> Читай этот файл **целиком** в начале работы — он задаёт архитектуру, обязательный процесс проверок (Gate)
> и требования SOLID / DRY / KISS / OWASP Top 10.
> Пользовательскую документацию (установка, быстрый старт, интеграция) — в `README.md`.

## 1. О проекте

- **Что это.** Filament-плагин **`geekcodev/filament-max-broadcasts`** — **массовые рассылки** пользователям
  MAX-мессенджера внутри Filament-панели. Строится поверх `geekcodev/laravel-max-client` (реестр чатов
  `max_chats`/`max_users`) и ядра `geekcodev/max-php-client` (Bot API MAX). Репозиторий/рабочая папка —
  `filament-max-broadcasts`, переиспользуемый автономный пакет.
- **Что даёт.** Filament-ресурс «Рассылки» (`BroadcastResource`): создание (текст HTML, тип «Новость/Акция», фото,
  отложенная отправка), сбор получателей из активных чатов `max_chats`, отправка через MAX API с санитизацией HTML и
  настраиваемыми кнопками-диплинками для акций, статусы `scheduled → running → completed/cancelled/failed` со
  счётчиками, очередь `SendBroadcastJob` с локом/батчами/ретраями/отменой, страницы список/создание/просмотр и relation
  manager получателей.
- **Принцип.** Плагин самодостаточен для рассылок: модели `Broadcast`/`BroadcastRecipient`, сервисы, job и Filament-
  ресурс живут внутри пакета. От хост-приложения он ожидает только: опубликованные миграции laravel-max-client
  (`max_chats`/`max_users`), рабочую очередь и настройку прав. Механизмы laravel-max-client не дублируются — используем
  пакетные модели/статусы.
- **Лицензия.** MIT (файл `LICENSE`).
- **Язык.** Рабочий язык общения с пользователем — **русский**; подписи UI — через lang-файлы (`lang/ru`, `lang/en`).

## 2. Ветки и состояние git

- `main` — стабильная, соответствует релизам; релиз — тег `vX.Y.Z`.
- `version` в `composer.json` **не указывается** — версия берётся из git-тегов.
- `.env`, `vendor/`, `composer.lock`, `.phpunit.cache/`, `build/`, `coverage/` — untracked (в `.gitignore`). **Никогда
  не коммитить секреты** (`MAX_API_TOKEN`, `MAX_WEBHOOK_SECRET`). Коммиты и push делает пользователь — без явного
  запроса не коммить.

## 3. Правила для ИИ-агентов

1. В начале работы прочитай `AGENTS.md` и `README.md`.
2. **Не коммить и не пушить без явного запроса пользователя.**
3. Перед завершением любой задачи, менявшей код, прогони обязательный Gate (раздел 7) целиком. Результаты не подменяй;
   недоступный шаг честно указывай в отчёте, а не пропускай молча.
4. Плагин реализуется как **самодостаточный пакет с нуля** — модели `Broadcast`/`BroadcastRecipient`, сервисы,
   `SendBroadcastJob` и Filament-ресурс живут внутри и не зависят ни от чего вне пакета (кроме `laravel-max-client`,
   `max-php-client` и ожиданий по миграциям/очереди). Источник истины по MAX API — `max-openapi` и пакетные классы
   `laravel-max-client`/`max-php-client` (см. раздел 9).
5. Не выдумывай сигнатуры MAX API: источник истины — `GeekCo\MaxPhpClient\ApiClient` и спецификация
   `https://github.com/geekcodev/max-openapi`. Отправка сообщений — только через пакетные сервисы (`BroadcastSender`).
6. Если для задачи чего-то не хватает (токен, сеть, контейнер) — скажи об этом, а не упрощай задачу молча.
7. Ответы — краткие и по делу; в коде — без лишних комментариев.
8. Текст в Markdown-файлах (AGENTS.md, README.md, RELEASE и др.) пиши как человек: связный текст, абзацы, а не сплошные
   списки из буллетов. Списки — только когда действительно перечисляешь однородные пункты.
9. `.env.example` — единственный эталон имён переменных плагина; при добавлении новой `FILAMENT_MAX_BROADCASTS_*`
   переменной синхронизируй его и `config/filament-max-broadcasts.php`.
10. По завершении каждой сессии заноси краткий итог (что сделано, какие решения/отклонения, прогресс по задачам) в
    журнал прогресса `.ai/progress/` (единый `JOURNAL.md` + отдельный файл в `sessions/`) — чтобы следующая сессия
    продолжалась с актуального места и прогресс был виден после `git clone`.
11. Если задача касается **Tailwind CSS** или **Livewire**, обращайся к локальным скиллам в
    `.ai/skills/tailwindcss-development/SKILL.md` и `.ai/skills/livewire-development/SKILL.md` соответственно — там
    эталонные практики, шаблоны и подводные камни этих технологий (Tailwind v4, Livewire v4). Загружай/используй их
    перед написанием или правкой кода с Tailwind-классами или Livewire-компонентами.

## 4. Структура репозитория (целевая)

```
config/filament-max-broadcasts.php    publishable-конфиг (--tag=filament-max-broadcasts-config)
database/migrations/                  миграции max_broadcasts / max_broadcast_recipients (грузятся из пакета)
lang/{ru,en}/broadcasts.php           подписи UI ресурса «Рассылки»
src/
  FilamentMaxBroadcastsServiceProvider.php  composition root: config/lang/migrations publish, биндинги сервисов
  FilamentMaxBroadcastsPlugin.php           Filament v5 plugin: ресурс рассылок в панели
  Enums/
    BroadcastStatus.php                scheduled|running|completed|cancelled|failed
    BroadcastType.php                  news|promo
    BroadcastRecipientStatus.php       pending|sent|failed
  Events/BroadcastCompleted.php        событие завершения рассылки
  Models/
    Broadcast.php                      max_broadcasts (creator(), recipients())
    BroadcastRecipient.php             max_broadcast_recipients (broadcast(), maxChat())
  Jobs/SendBroadcastJob.php            очередь: лок, батчи, ретраи, отмена, счётчики
  Services/
    BroadcastService.php               create(): сбор получателей + создание + dispatch
    BroadcastTextSanitizer.php         санитизация HTML под whitelist тегов MAX + toMaxHtml()
    BroadcastRecipientsResolver.php    выбор получателей (активные чаты, дедуп по chat_id) — расширяемый
    BroadcastSender.php                отправка в MAX: текст/фото/промо-кнопки (uploadMedia + sendMessage)
  Support/PromoButtons.php             сборка кнопок-диплинков акций из конфига
  Resources/
    BroadcastResource.php              Filament-ресурс «Рассылки»
    Schemas/BroadcastForm.php          форма создания/просмотра
    Tables/BroadcastsTable.php         список (колонки, фильтры, действия)
    Pages/{CreateBroadcast,ListBroadcasts,ViewBroadcast}.php
    RelationManagers/BroadcastRecipientsRelationManager.php
tests/                                PHPUnit + Orchestra Testbench
  Fixtures/                            AdminPanelProvider, TestUser, миграция users, Gate broadcasts.*
  Unit/                                enums, models, sanitizer, resolver, promo buttons, sender, service, job
  Feature/                             BroadcastResource (листинг/создание/просмотр/действия)
Dockerfile                            PHP 8.4 (ghcr.io/geekcodev/php) + опциональный Xdebug
docker-compose.yml                    сервис app, user 1000:1000, volume ./
docker/config/usr/local/etc/php/conf.d/40-custom.ini  PHP-конфиг dev-контейнера (memory_limit=1G)
composer.json                         PSR-4 GeekCo\FilamentMaxBroadcasts\, PHP ^8.4
phpunit.xml                           failOnRisky/failOnWarning; SQLite in-memory
phpstan.neon                          level max (Larastan), configDirectories → config/
.php-cs-fixer.dist.php                PSR-12 + declare_strict_types + no_unused_imports
.env.example                          эталон имён переменных (FILAMENT_MAX_BROADCASTS_*)
```

`resources/views/`, Livewire-компонентов и real-time (Echo/Reverb) на текущем этапе **нет** — рассылки целиком на
Filament-компонентах.

## 5. Архитектура и ключевые контракты

- **Подключение**: `->plugin(FilamentMaxBroadcastsPlugin::make())` в PanelProvider. Регистрирует ресурс
  `BroadcastResource`. Права (строки, `$user->can(...)`, совместимо со spatie/laravel-permission и Gate):
  `permissions.view` (`broadcasts.view`), `permissions.create` (`broadcasts.create`), `permissions.send`
  (`broadcasts.send`), `permissions.manage` (`broadcasts.manage`).
- **Получатели**: `BroadcastRecipientsResolver` — единственный источник списка `MaxChat` для рассылки (активные чаты,
  дедуп по `chat_id`, сортировка по `last_activity_at`). Модель чата — `config('filament-max-broadcasts.chats_model')`
  (по умолчанию пакетный `GeekCo\LaravelMaxClient\Models\MaxChat`), статус — `MaxChatStatus::Active`.
- **Создание рассылки**: `BroadcastService::create(text, scheduledAt, creator, imagePath, type)` — резолвит получателей,
  сохраняет `Broadcast` + `BroadcastRecipient`s, `dispatch()` через `SendBroadcastJob` (с `delay()` при будущем
  расписании). Текст санитизируется `BroadcastTextSanitizer->sanitize()` при создании.
- **Отправка**: `SendBroadcastJob` — `Cache::lock("broadcast:{id}")`, статус `running`, батчи по `queue.batch_size`
  (25) с проверкой отмены и обновлением счётчиков, `sendTo()` через `BroadcastSender->send(new Recipient(chatId,
  userId), toMaxHtml(text), imagePath, type)`, по завершении — `completed` + `BroadcastCompleted`. `failed()` →
  `failed`.
- **BroadcastSender** — единственная точка отправки рассылки: загрузка фото (`uploadMedia`), для «Акции» — кнопки от
  `PromoButtons` (InlineKeyboard), сообщение с `format=html`. Прямые вызовы ApiClient из Filament/Page запрещены.
- **Санатизация**: `BroadcastTextSanitizer` (whitelist тегов MAX, drop script/style, unwrap неизвестных) + `toMaxHtml()`
  — разворачивание `<p>`/`<div>`/`<br>` в `\n`, иначе MAX не рендерит абзацы. Применяется и при создании, и перед
  отправкой.
- **Промо-кнопки**: `PromoButtons` собирает кнопки из `config('filament-max-broadcasts.bot_username')` и
  `promo_buttons` (список `{text, startapp}`) → URL `https://max.ru/<bot>?startapp=<param>`. Пусто при пустом
  `bot_username`
  или пустом списке.
- **Таблицы**: `max_broadcasts` и `max_broadcast_recipients` (структура — наследие `broadcasts`/`broadcast_recipients`
  из хоста, но с префиксом `max_` во избежание конфликтов). FK `created_by` → таблица `users` (модель —
  `config('filament-max-broadcasts.user_model')`). Миграции грузятся из пакета автоматически.
- **Переопределение моделей**: `broadcast_model`/`recipient_model`/`chats_model`/`user_model` — конфигурируемы.

### Соглашения

- PHP **8.4**, `declare(strict_types=1)` во всех файлах, PSR-12, PHPStan **level max** (Larastan).
- Namespace `GeekCo\FilamentMaxBroadcasts` (тесты `GeekCo\FilamentMaxBroadcasts\Tests`), PSR-4.
- SOLID / DRY / KISS: тонкие Filament-страницы (вызов сервисов), логика — в сервисах/job; без дублирования
  laravel-max-client и без копирования логики обратно в хост-приложение.
- Не добавлять комментарии без необходимости. Имена — английские; русские тексты — в lang-файлах и тестах.
- Enum-ы: русские подписи через `->label()`/`labels()`, не хардкод в представлениях.
- Тесты обязательны для нового кода: unit — сервисы/sanitizer/enums/models/job; feature — Filament-ресурс через
  Testbench + фикстуры (`tests/Fixtures`: панель, TestUser, Gate). Моки `ApiClient`/`BroadcastSender` — через
  `$this->mock()`.

## 6. Локальная разработка

PHP/Composer на хосте не требуются — всё через Docker:

```bash
docker compose up -d --build
docker compose run --rm app composer install
docker compose exec app composer test      # PHPUnit
docker compose exec app composer analyse   # PHPStan level max
docker compose exec app composer lint      # php-cs-fixer --dry-run
docker compose exec app composer format    # php-cs-fixer fix
docker compose exec app composer audit     # composer audit
```

## 7. Обязательный Gate перед завершением задачи

1. **Lint PHP**: `composer lint` (php-cs-fixer --dry-run) → 0 ошибок; при правках — `composer format`.
2. **Статика**: `composer analyse` (PHPStan level max) → 0 ошибок.
3. **Тесты**: `composer test` (PHPUnit) → зелёные (failOnRisky/failOnWarning).
4. **Audit**: `composer audit` → 0 критичных.

Все шаги обязательны. Недоступный шаг — честно в отчёт.

## 8. OWASP Top 10 (обязательно при написании кода)

- **A01** — доступ к ресурсу и действиям только по правам (`permissions.*`); fail-closed, без прав — недоступно.
- **A02** — секреты только в env; не логировать токены.
- **A03** — текст рассылки санитизируется `BroadcastTextSanitizer` (whitelist, безопасные схемы href) до сохранения и до
  отправки; Blade/формы экранируются по умолчанию.
- **A04** — `WebAppData`/получатели не контролируются пользователем из формы (ветка выбора чатов — серверная логика);
  лимиты фото (`image.max_kb`), лимит текста (4000).
- **A05** — publishable-конфиг с безопасными дефолтами; секреты не в коде.
- **A06** — `composer audit` в Gate; lock-файлы актуальны.
- **A07** — права строкой через `$user->can(...)` (spatie/Gate); постоянновременные сравнения — зона ответственности
  laravel-max-client.
- **A09** — ошибки отправки в MAX логируются без чувствительных данных (`failed()` в `SendBroadcastJob`, catch в
  `BroadcastSender`); исключения не глушатся молча.

## 9. Источник истины (MAX API)

- Спецификация: `https://github.com/geekcodev/max-openapi` (OpenAPI 3.1), сервер `https://platform-api2.max.ru`.
- Отправка рассылки: `ApiClient::sendMessage` (`NewMessageBody`, `TextFormat::Html`), `uploadMedia` для фото,
  `InlineKeyboardButton`/`ButtonType::Link` для кнопок-диплинков акций. Сигнатуры брать из пакета
  `geekcodev/max-php-client`, не выдумывать.
- Реестр чатов/пользователей — из `geekcodev/laravel-max-client` (`MaxChat`, `MaxUser`, `MaxChatStatus`).
