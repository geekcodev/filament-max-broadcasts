# AGENTS.md

> Проектный контекст и рабочие правила для разработчиков и ИИ-агентов (включая opencode).
> Читай этот файл **целиком** в начале работы — он задаёт архитектуру, обязательный процесс проверок (Gate)
> и требования SOLID / DRY / KISS / OWASP Top 10.
> Пользовательскую документацию (установка, быстрый старт, интеграция) — в `README.md`.

## 1. О проекте

- **Что это.** Filament-плагин **`geekcodev/filament-max-broadcasts`** — **массовые рассылки** пользователям
  MAX-мессенджера внутри Filament-панели. Строится поверх `geekcodev/laravel-max-client` (реестр чатов
  `max_chats`/`max_users`, вебхук-доставка апдейтов) и ядра `geekcodev/max-php-client` (Bot API MAX).
  Репозиторий/рабочая папка — `filament-max-broadcasts`, переиспользуемый автономный пакет.
- **Что даёт.** Filament-ресурс «Рассылки» (`BroadcastResource`): создание (текст HTML, тип «Новость/Акция»,
  медиавложения — картинки/видео/файлы, отложенная отправка), выбор получателей — несколько сегментов и/или конкретные
  чаты (снимок `recipient_chat_ids`, повтор тем же людям), отправка через MAX API с санитизацией HTML и настраиваемыми
  кнопками-диплинками, статусы `scheduled → running → completed/cancelled/failed` со счётчиками, очередь
  `SendBroadcastJob` с локом/батчами/ретраями/отменой, страницы список/создание/просмотр и relation manager
  получателей. Плюс два смежных модуля: сегменты получателей (`BroadcastSegmentResource` — именованные группы чатов)
  и opt-in согласие на рассылку (callback-кнопки MAX, сегмент «Новости и акции», действие «Запросить согласие» на
  списке рассылок) с журналом согласий `BroadcastConsentResource`.
- **Принцип.** Плагин самодостаточен для рассылок: модели `Broadcast`/`BroadcastRecipient`, сервисы, job и Filament-
  ресурс живут внутри пакета. От хост-приложения он ожидает только: опубликованные миграции laravel-max-client
  (`max_chats`/`max_users`), рабочую очередь и настройку прав. Механизмы laravel-max-client не дублируются — используем
  пакетные модели/статусы.
- **Лицензия.** MIT (файл `LICENSE`).
- **Язык.** Рабочий язык общения с пользователем и всех md-файлов — **русский**; подписи UI — через lang-файлы
  (`lang/ru`, `lang/en`).

### Статус и версии

Актуальную версию **всегда сверяй по источникам, а не по цифрам в этом файле**: constraint — в `composer.json`,
реально установленная — `composer show geekcodev/laravel-max-client` и `composer show geekcodev/max-php-client` (по
одному пакету за раз: два аргумента `composer show` не принимает), релизные теги — `git tag --sort=-v:refname` здесь и
в соседних `../laravel-max-client`, `../max-php-client`.

Constraint `geekcodev/laravel-max-client: ^1.2.0` и `geekcodev/max-php-client: ^1.1.8` (v1.1.0, 2026-10-02) закрыл
долговое решение пункта 1 плана: поддерживаемой считается форма 1.2, а код проверен на обеих формах реестра.
Плагин читает из `max_chats` поля `status`, `chat_type`, `last_activity_at` и фильтрует `status = active`, но **форма
реестра различается** между версиями ядра, и от неё зависят ключ строки, связи чат-пользователь и колонка `title`:

- **1.1.x** — строка на пару (пользователь, чат): есть колонка `user_id` и `unique(user_id, chat_id)`, поэтому один
  чат присутствует в реестре несколькими строками (дедуп по `chat_id` в `BroadcastRecipientsResolver` обязателен), а
  `chat_id` объявлен `unsignedBigInteger` — MySQL отрицательные идентификаторы групп и каналов отвергнет
  (на PostgreSQL `unsignedBigInteger` это знаковый `bigint`, то есть проблема специфична для MySQL);
- **1.2.0** — строка на чат: первичный ключ `chat_id` (знаковый `bigInteger`), связи чат-пользователь вынесены в
  `max_chat_users` (`chatUsers`, `maxUsers`), есть `title`, метаданные и `status` обязательны.

Различия скрыты адаптером `Support\ChatRegistry` (единственное место в `src/`, которое знает про форму реестра):
`userRelation()`, `withUsers()`, `whereUserLike()`, `user()`, `userId()`, `displayName()`. Вне адаптера обращаться к
`maxUser`, `user_id` и `title` чата нельзя — на другой форме реестра этого просто нет. Проверка версии идёт через
`composer show`, а Gate дополнительно проверяется в условиях CI (gotcha 11): `composer.lock` не коммитится, поэтому CI
ставит пакеты без него. Если форма реестра у адаптера поменяется — план перевода, а не молчаливая правка кода. При
обновлении хоста, который переходит на форму 1.2, порядок обязателен: `php artisan migrate`, затем
`php artisan max:upgrade` — без второго шага `max_chat_users` останется пустым, и плагин не найдёт получателей.

### Регрессии не чиним в стороннем проекте

Если расхождение или пробел обнаружен в интеграционном Laravel-приложении, использующем этот пакет, — это регрессия
плагина, а не особенность приложения. Заводи задачу здесь (тест + фикс + релиз) и не предлагай обход в стороннем
репозитории, если обход маскирует дефект самого пакета.

## 2. Ветки, git и релизы

- `dev` — рабочая ветка разработки; `main` — стабильная, соответствует релизам; feature-ветки — `feat/*` и сливаются в
  `dev`. Релиз — тег `vX.Y.Z`.
- `version` в `composer.json` **не указывается** — версия берётся из git-тегов.
- `.env`, `vendor/`, `composer.lock`, `.phpunit.cache/`, `.phpstan-cache/`, `build/`, `coverage/`,
  `.php-cs-fixer.cache`, `*.log` — untracked (в `.gitignore`). **Никогда не коммитить секреты** (`MAX_API_TOKEN`,
  `MAX_WEBHOOK_SECRET`). Каталог `.agents/` наоборот **коммитится** — см. 4.1.
- Коммиты и push делает пользователь. **Не коммить и не пуши без явного запроса.**
- **Различай «текст коммита» и «коммит».** «Напиши текст коммита» — верни только текст, коммит не создавай.
  «Закоммить» — создай коммит. Не смешивай эти запросы.
- **«Напиши краткий текст коммита» — это только head, без body.** Возвращай одну строку: subject на английском по
  Conventional Commits, собранный по всем изменениям ветки (не по последнему действию). Body в такой ответ не пиши, даже
  если изменения большие: краткий текст = head. Полный текст с body пользователь может попросить отдельно («полный текст
  коммита», «с body»).
- **Текст коммита — на английском, по Conventional Commits, по всем изменениям ветки.** Subject (head) составляется из
  реального diff (`git status --short`, `git diff`, `git log`), а не из последнего действия: `feat:`, `fix:`, `docs:`,
  `chore:`, `refactor:`, `test:` — с маленькой буквы, без точки в конце, до 72 символов. Он должен кратко отражать
  **все** изменения ветки целиком, а не только самую заметную часть. Body — только когда его просят: с переносами на 100
  символов, с описанием «что изменилось и почему», без пересказа кода. Ломающий переход или миграция данных — либо
  `feat!:` с `BREAKING CHANGE:`, либо minor-релиз.
- **Соответствие формулировки запроса и ответа** (не додумывай за пользователя):

  | Запрос пользователя | Что делаешь                                                                                |
  |--------------------|--------------------------------------------------------------------------------------------|
  | «краткий текст коммита», «напиши коммит-сообщение», «subject» | Одна строка — head, без body, по Conventional Commits, по всем изменениям ветки. Коммит не создаёшь |
  | «текст коммита» без уточнений | То же самое: краткий head без body                                                               |
  | «полный текст коммита», «с body» | Head + body с переносами по 100 символов                                                        |
  | «закоммить», «сделай коммит» | Собираешь текст сам (по правилам выше) и создаёшь коммит явным списком файлов               |
  | «запушь», «пушни» | Только push; коммит при этом не создаётся, если о нём не просили                              |

  Если в ветке смешанные изменения (код + документация + инфраструктура), head обязан отражать все группы
  изменений, а не только самую заметную: например, `docs: rework agent workflow and add coverage gate`.
  Формулировки вроде «напиши коммит» без уточнения «краткий» считай запросом краткого head — уточняй только
  тогда, когда без этого ответ может быть двусмысленным (например, просили body явно, но не сказали про head).
- Перед коммитом обязательно `git status --short` и `git diff`: в индекс добавляй **явный список файлов**, а не
  `git add .`/`git add -A`. **Перед релизом рабочее дерево должно быть чистым**, кроме ожидаемых файлов релиза.
- **Миграции, уже выполненные у хоста, не переименовывать и не править на месте.** Файл миграции уехал в установку под
  своим именем и записан в таблицу `migrations`; переименование или правка тела молча ничего не делает у существующих
  установок, а сдвиг нумерации (например, `000005 → 000006`) приводит к повторному `Schema::create` и падению
  «таблица уже есть». Новая колонка или таблица — только новая ad-hoc миграция. Gotcha 2.
- **Релиз**: release-notes в `.agents/release/RELEASE_NOTES_vX.Y.Z.md` → merge `dev → main` →
  `git tag vX.Y.Z && git push origin vX.Y.Z` → GitHub Release из тега → Packagist (обновляется по webhook). Значимые
  пункты релиза продублировать в `README.md` (раздел «История изменений»).
- **Формат release-notes**: `Новое` / `Изменение (BC)` / `Затронутые сценарии` / `Качество` (тесты, покрытие, аудит).
  Файл не удаляется после релиза — история остаётся в `.agents/release/`. Ранее эти файлы лежали в корне как
  `RELEASE-vX.Y.Z.md` и были локальными; с 2026-10-02 ведутся в `.agents/release/` и в git.

## 3. Правила для ИИ-агентов

1. В начале работы прочитай `AGENTS.md`, `README.md` и активный план `.agents/plans/PLAN-filament-max-broadcasts.md`
   целиком. Примеры документированного workflow — соседние пакеты: `filament-max-chat`
   (`/home/user/web/filament-max-chat/`) и `laravel-max-client` (`/home/user/web/laravel-max-client/`); источник
   истины по API MAX — ядро `../max-php-client/docs/api-reference.md` (раздел 9).
2. **Не коммить и не пуши без явного запроса пользователя.**
3. **Запрос про текст коммита не равен запросу на коммит.** «Краткий текст коммита» (и «текст коммита» без
   уточнений) — это ровно одна строка: head на английском по Conventional Commits, собранный по всем изменениям
   ветки, а не по последнему действию. Body в такой ответ не пиши даже при больших изменениях, коммит не создавай и
   не запушивай. Body — только если попросили явно («полный текст коммита», «с body»); «закоммить» — создай коммит;
   «запушь» — только push. Полная таблица соответствий и требования к head — в §2.
4. Перед завершением любой задачи, менявшей код, прогони обязательный Gate (раздел 7) целиком и сверься с чек-листом
   (раздел 11). Результаты не подменяй; недоступный шаг честно указывай в отчёте, а не пропускай молча.
5. Плагин реализуется как **самодостаточный пакет с нуля** — модели
   `Broadcast`/`BroadcastRecipient`/`BroadcastSegment`/`BroadcastConsent`, сервисы, jobs
   (`SendBroadcastJob`/`SendConsentRequestsJob`), слушатель `HandleConsentCallback` и Filament-ресурсы живут внутри и не
   зависят ни от чего вне пакета (кроме `laravel-max-client`, `max-php-client` и ожиданий по миграциям/очереди).
   Механизмы laravel-max-client не дублируются, логика обратно в хост-приложение не копируется.
6. Не выдумывай сигнатуры MAX API: источник истины — `GeekCo\MaxPhpClient\ApiClient`, `docs/api-reference.md` ядра и
   спецификация `https://github.com/geekcodev/max-openapi`. Отправка сообщений — только через `BroadcastSender`.
   Прямые вызовы `ApiClient` из Filament-ресурсов, страниц и действий запрещены.
7. Если для задачи чего-то не хватает (токен, сеть, контейнер, драйвер покрытия) — скажи об этом, а не упрощай задачу
   молча.
8. Ответы — краткие и по делу; в коде — без лишних комментариев и без декоративных символов (никаких «ёлочек», эмодзи,
   псевдографики, иероглифов в сообщениях, именах и документации): только русский и английский языки.
9. Текст в Markdown-файлах (AGENTS.md, README.md, release-notes, планы, журнал) пиши как человек: связный текст,
   абзацы, а не сплошные списки из буллетов. Списки — только когда действительно перечисляешь однородные пункты (Gate,
   gotchas, чек-лист).
10. `.env.example` — единственный эталон имён переменных плагина; при добавлении новой `FILAMENT_MAX_BROADCASTS_*`
   переменной синхронизируй его и `config/filament-max-broadcasts.php`.
11. Любой ключ перевода, который ты добавил в код, обязан существовать и в `lang/ru`, и в `lang/en` — иначе UI покажет
    сырые ключи, а тесты этого не поймают.
12. По завершении каждой сессии заноси итог по формату из 4.1: строка в `.agents/journals/JOURNAL.md` + файл в
    `.agents/journals/sessions/`, отмечай выполненные пункты в `.agents/plans/PLAN-filament-max-broadcasts.md`.
    Каталог `.agents/` **коммитится** — прогресс должен быть виден после `git clone`.
13. Перед правкой проверь чужие ловушки из раздела 10, а не открывай их заново.
14. Если задача касается **Tailwind CSS** или **Livewire**, обращайся к локальным скиллам в
    `.agents/skills/tailwindcss-development/SKILL.md` и `.agents/skills/livewire-development/SKILL.md` соответственно —
    там эталонные практики, шаблоны и подводные камни этих технологий (Tailwind v4, Livewire v4).
15. Локальный результат Gate не является окончательным: `vendor/` и `composer.lock` в репозитории не хранятся, поэтому
    они скрывают дрейф версий, а ручная подмена содержимого `vendor/` делает зелёный результат недостоверным
    (gotcha 11). Перед завершением любой задачи, менявшей код или зависимости, Gate обязательно повторяется в условиях
    CI — в чистой копии дерева без `vendor/` и `composer.lock`, со свежей установкой по `composer.json`, то есть ровно так,
    как это делает workflow. Локальный прогон по подменённому `vendor/` записывается в отчёт как один из наборов
    зависимостей, но не закрывает Gate.

## 4. Структура репозитория

```
config/filament-max-broadcasts.php    publishable-конфиг (--tag=filament-max-broadcasts-config)
database/migrations/                  миграции max_broadcasts / max_broadcast_recipients / max_broadcast_attachments /
                                      max_broadcast_segments / max_broadcast_consents / pivot max_broadcast_segment
                                      + 2026_10_02_000001_make_broadcast_recipient_ids_signed
                                      (знаковые chat_id/user_id под отрицательные id групп и каналов; Schema
                                      API, поэтому работает на MySQL и PostgreSQL, no-op на SQLite; загрузка из
                                      пакета; у хоста уже выполненные — не переименовывать, §2)
lang/{ru,en}/broadcasts.php           подписи UI ресурса «Рассылки»
src/
  FilamentMaxBroadcastsServiceProvider.php  composition root: config/lang/migrations publish, биндинги сервисов,
                                      регистрация HandleConsentCallback на MaxUpdateReceived
  FilamentMaxBroadcastsPlugin.php           Filament v5 plugin: ресурсы «Рассылки», «Сегменты», «Согласия» в панели
  Enums/
    BroadcastStatus.php                scheduled|running|completed|cancelled|failed
    BroadcastRecipientStatus.php       pending|sent|failed
    BroadcastConsentAction.php         opt_in|opt_out (ответы на запрос согласия)
    BroadcastTypes/
      News.php, Promo.php              типы рассылок — backed-enum'ы, реализующие BroadcastTypeContract
      ConsentPoll.php                  тип-источник callback-кнопок согласия (для SendConsentRequestsJob)
  Contracts/BroadcastTypeContract.php  контракт типа рассылки (label/buttonRows/badgeColor)
  Events/BroadcastCompleted.php        событие завершения рассылки
  Models/
    Broadcast.php                      max_broadcasts (creator(), recipients(), segments(), attachments())
    BroadcastRecipient.php             max_broadcast_recipients (broadcast(), maxChat())
    BroadcastAttachment.php            max_broadcast_attachments (broadcast(), uploadType)
    BroadcastSegment.php               max_broadcast_segments — именованные группы чатов (chat_ids JSON, chat_count)
    BroadcastConsent.php               max_broadcast_consents — факт opt_in/opt_out по паре (segment_id, chat_id)
  Jobs/
    SendBroadcastJob.php               очередь рассылки: лок, батчи, ретраи, отмена, счётчики
    SendConsentRequestsJob.php         очередь запроса согласия: лок, пере-резолв кандидатов, батчи
  Listeners/HandleConsentCallback.php  приём callback-ответов согласия (MaxUpdateReceived) + ответ sendAnswer
  Services/
    BroadcastService.php               create(): резолв получателей (chatIds → сегменты → все активные) + dispatch
    BroadcastTextSanitizer.php         санитизация HTML под whitelist тегов MAX + toMaxHtml()
    BroadcastRecipientsResolver.php    источник MaxChat (активные чаты, дедуп по chat_id) — расширяемый
    BroadcastSender.php                отправка в MAX: текст/медиавложения/кнопки-диплинки (uploadMedia + sendMessage)
    ConsentService.php                 единая точка согласий: optIn/optOut/answeredChatIds, сегмент «Новости и акции»
    ConsentRequestService.php          кандидаты на запрос согласия (активные без ответа) + dispatch джобы
  Support/
    BroadcastTypes.php                 реестр типов из конфига (instance/contains/options/label/badgeColor)
    BroadcastTypeDefaults.php          трейт дефолтного поведения типов (lang-подпись, кнопки из per_type)
    ChatSelectionField.php             мультивыбор чатов с серверным поиском (Select: лимит 50, дедуп, фолбэк label)
    ChatRegistry.php                   единственный адаптер к реестру чатов: форма 1.1.x против 1.2 (связи, user_id,
                                      title, displayName)
  Resources/
    BroadcastResource.php              Filament-ресурс «Рассылки»
    BroadcastSegmentResource.php       Filament-ресурс «Сегменты» (список/создание/редактирование)
    BroadcastConsentResource.php       Filament-ресурс «Согласия» (журнал ответов, read-only)
    Schemas/{BroadcastForm,BroadcastSegmentForm}.php
    Tables/{BroadcastsTable,BroadcastSegmentsTable,BroadcastConsentsTable}.php
    Pages/{CreateBroadcast,ListBroadcasts,ViewBroadcast,ListBroadcastSegments,CreateBroadcastSegment,EditBroadcastSegment,
           ListBroadcastConsents}.php
    RelationManagers/BroadcastRecipientsRelationManager.php
tests/                                PHPUnit + Orchestra Testbench
  Fixtures/                            AdminPanelProvider, TestUser, OffersType, OffersSegment, миграция users, Gate
                                      broadcasts.*; Chats/RawChat/RawUser — чаты и пользователи реестра, одинаковые
                                      для обеих форм (gotcha 1); Queue::fake() и порядок provider'ов — в TestCase
  Support/                             MockHttpClient — подмена транспорта PSR-18 для ApiClient
  Unit/                                enums, models, sanitizer, resolver, types, sender, service, job, consent, plugin,
                                      адаптер реестра чатов, метаданные ресурса согласий
  Feature/                             BroadcastResource + BroadcastSegmentResource + BroadcastConsentResource +
                                      relation manager получателей
scripts/check-coverage.php            проверка порога покрытия по build/coverage.xml (дефолт 95% —
                                      тот же, что в composer.json; аргумент переопределяет)
.github/workflows/ci.yml              один job quality: lint → phpstan → phpunit + coverage gate → audit
.agents/                              рабочая память проекта: skills/, plans/, release/, journals/ (см. 4.1)
Dockerfile                            PHP 8.4 (ghcr.io/geekcodev/php) + опциональный Xdebug
docker-compose.yml                    сервис app, user 1000:1000, volume ./
docker/config/usr/local/etc/php/conf.d/40-custom.ini  PHP-конфиг dev-контейнера (memory_limit=1G)
composer.json                         PSR-4 GeekCo\FilamentMaxBroadcasts\, PHP ^8.4
phpunit.xml                           failOnRisky/failOnWarning; SQLite in-memory; source → src/
phpstan.neon                          level max (Larastan), paths: src/tests/config/database/scripts,
                                      configDirectories → config/, tmpDir → .phpstan-cache
phpstan-baseline.neon                 только test-only записи для хелперов Filament/Livewire
.php-cs-fixer.dist.php                PSR-12 + declare_strict_types + no_unused_imports (finder: src, tests, config,
                                      database, scripts)
.gitattributes                        export-ignore для .agents/, tests/, .github/, dev-конфигов, Dockerfile,
                                      docker/ и docker-compose.yml: чистый dist для Packagist
.env.example                          эталон имён переменных (FILAMENT_MAX_BROADCASTS_*)
```

`resources/views/`, Livewire-компонентов и real-time (Echo/Reverb) на этом этапе **нет** — рассылки целиком на
Filament-компонентах. `composer.lock`, `.phpunit.cache/`, `.phpstan-cache/`, `vendor/`, `build/` — в `.gitignore`;
рабочая память `.agents/` в git, но исключена из архива пакета через `.gitattributes`.

### 4.1. Прогресс, планы и release-notes

`.agents/` — единственное место рабочей памяти проекта: журнал, планы, release-notes и локальные скиллы лежат только
здесь. Каталог **коммитится** (осознанное отличие от `laravel-max-client`, где `.agents/` в `.gitignore`): смысл в том,
чтобы следующая сессия — в том числе на другой машине после `git clone` — продолжила с актуального места. Чтобы рабочая
память не попадала в публичный архив пакета, каталог перечислен в `.gitattributes` с `export-ignore` (то же для
`tests/`, `.github/` и файлов статики), а секретов в `.agents/` не пишется по правилам ниже. До 2026-10-02 рабочая
память лежала в `.ai/progress/`, а release-notes — в корне как `RELEASE-vX.Y.Z.md`; старые пути не используются.

```
.agents/
  skills/                             локальные скиллы: tailwindcss-development/, livewire-development/
  plans/                              многошаговые планы
  release/                            release-notes версий
  journals/
    JOURNAL.md                        таблица сессий, новые сверху
    sessions/                         подробности сессий
```

- `plans/PLAN-filament-max-broadcasts.md` — активный план проекта: задачи, которые переживают одну сессию; шаги
  отмечаются в нём, дублировать в другие файлы не надо. Отдельные задачи оформляются как
  `plans/YYYY-MM-DD-<слаг>.md`.
- `release/RELEASE_NOTES_vX.Y.Z.md` — release-notes версий, по одной на версию, в своём формате; файлы не удаляются,
  история остаётся в git.
- `journals/JOURNAL.md` — таблица сессий, новые сверху, колонки `Дата`, `Файл`, `Теги`, `Описание`; в описании — что
  сделано и результат Gate. Над таблицей — заголовок и пояснение формата. Подробности — в файле сессии, здесь только
  указатель.
- `journals/sessions/YYYY-MM-DD-<слаг>.md` — тело сессии: YAML-frontmatter (`tags`, `date`), заголовок `# <тема>` без
  даты (дата — в имени файла и во frontmatter), затем секции `Проблема`, `Решение`, `Тесты`, `Нюансы`, `Gate`. Слаг
  латиницей в kebab-case. Только факты и решения; пересказ кода и длинные логи не пишем.
- `skills/` — локальные практики по Tailwind и Livewire, на которые ссылается правило 14. Формат как у скиллов
  opencode: `SKILL.md` с frontmatter `name`/`description`, при необходимости — каталог `reference/`.

### Что куда писать

| Вопрос                                   | Файл                                                                  |
|------------------------------------------|-----------------------------------------------------------------------|
| «Как устроен проект и что нельзя делать» | `AGENTS.md` — контракты, соглашения, Gate, gotchas, чек-лист          |
| «Что делаем сейчас и в каком порядке»    | `.agents/plans/PLAN-filament-max-broadcasts.md` — шаги, критерии готовности |
| «Что произошло в конкретной сессии»      | `.agents/journals/sessions/*.md` + строка в `JOURNAL.md`              |
| «Что вошло в релиз X.Y.Z»                | `.agents/release/RELEASE_NOTES_vX.Y.Z.md`                             |
| «Как пользоваться такими технологиями»   | `.agents/skills/*/SKILL.md`                                            |
| «Как этим пользоваться» (для хоста)      | `README.md` — установка, конфигурация, интеграция, история            |

Правило: постоянное решение — в `AGENTS.md`; решение по конкретной задаче — в плане; факт о сессии — в журнале. Текст
правил в плане и журнале не дублируем, даём ссылку на раздел `AGENTS.md`.

Чего в `.agents/` **не** пишем: токены и секреты, payload и тела ответов MAX API, содержимое чужих репозиториев,
устаревшие рассуждения. Не выдумывай результаты проверок: недоступный шаг Gate пишется как недоступный.

## 5. Архитектура и ключевые контракты

- **Подключение**: `->plugin(FilamentMaxBroadcastsPlugin::make())` в PanelProvider. Регистрирует ресурсы
  `BroadcastResource`, `BroadcastSegmentResource` и `BroadcastConsentResource` (переопределяются через `->resource()` /
  `->segmentResource()` / `->consentResource()`). Права (строки, `$user->can(...)`, совместимо с spatie/laravel-permission
  и Gate): `permissions.view` (`broadcasts.view`), `permissions.create` (`broadcasts.create`),
  `permissions.manage` (`broadcasts.manage`).
- **Источник чатов**: `BroadcastRecipientsResolver` — источник списка `MaxChat` для рассылки (активные чаты, дедуп по
  `chat_id`, сортировка по `last_activity_at`). Модель чата — `config('filament-max-broadcasts.chats_model')`
  (по умолчанию пакетный `GeekCo\LaravelMaxClient\Models\MaxChat`), статус — `MaxChatStatus::Active`. Дедуп обязателен
  и на форме реестра 1.1.x, где строка на пару (пользователь, чат), и на 1.2.0, где строка на чат.
- **Получатели**: `BroadcastService::create(..., ?array $chatIds = null, array $segments = [])`. Приоритет резолва:
  явные `chatIds` → объединение `chat_ids` выбранных сегментов → все активные чаты. Снимок фактических получателей
  хранится в `recipient_chat_ids` рассылки (nullable, `[]` = всем активным) — источник истины для повтора; выбранные
  сегменты привязываются через pivot `max_broadcast_segment` (история выбора). В UI — мультивыбор сегментов
  (`Select multiple`) + ручная корректировка поиском активных чатов (серверно, только найденные); «Повторить» передаёт и
  сегменты, и снимок.
- **Создание рассылки**: `BroadcastService::create(text, scheduledAt, creator, attachments, type, chatIds, segments)` —
  резолвит получателей, сохраняет `Broadcast` + вложения (`attachments`, `list<array{upload_type, path}>`) +
  `BroadcastRecipient`s, `segments()->sync()`, `dispatch()` через `SendBroadcastJob` (с `delay()` при будущем расписании).
  Текст санитизируется `BroadcastTextSanitizer->sanitize()` при создании; невалидное вложение (несуществующий
  `UploadType`/пустой path) — `InvalidArgumentException 'Invalid broadcast attachment #%d.'`.
- **Отправка**: `SendBroadcastJob` — `Cache::lock("broadcast:{id}")`, статус `running`, батчи по `queue.batch_size`
  (25) с проверкой отмены и обновлением счётчиков, `sendTo()` через `BroadcastSender->send(new Recipient(chatId,
  userId), toMaxHtml(text), $media, type)`, где `$media` — `list<array{upload_type, path}>` из вложений рассылки; по
  завершении — `completed` + `BroadcastCompleted`. `failed()` → `failed`. Лок защищает от повторной отправки только при
  общем для всех воркеров хранилище кэша (redis/database/memcached): на `file`/`array` два процесса возьмут одну
  рассылку — gotcha 15.
- **BroadcastSender** — единственная точка отправки рассылки: загрузка медиавложений (картинки/видео/файлы через
  `uploadMedia`, `UploadType→AttachmentType` match, чтение по локальному пути `Storage::disk(...)->path(...)`, публичный
  URL хранилища не нужен), кнопки-диплинки (InlineKeyboard) от `BroadcastTypeContract::buttonRows()`, сообщение с
  `format=html`. Прямые вызовы ApiClient из Filament/Page запрещены.
- **Санатизация**: `BroadcastTextSanitizer` (whitelist тегов MAX, drop script/style, unwrap неизвестных) + `toMaxHtml()`
  — разворачивание `<p>`/`<div>`/`<br>` в `\n`, иначе MAX не рендерит абзацы. Применяется и при создании, и перед
  отправкой.
- **Типы и кнопки**: типы рассылок — это поведение. Реестр `types` из конфига отображает токен на класс, реализующий
  `BroadcastTypeContract` (default: `Enums\BroadcastTypes\{News,Promo}`, каждый — backed-enum с case value === токен).
  Простейший тип строится на трейте `BroadcastTypeDefaults`: подпись из lang `broadcasts.type.<token>`, кнопки из
  `bot_username` + `buttons.per_type.<token>` (`list<InlineKeyboardButtonRow>`, URL
  `https://max.ru/<bot>?startapp=<param>`), цвет badge `gray`. Хост добавляет тип = свой enum (трейт + нужные
  переопределения `label()/buttonRows()/badgeColor()`) + строка в `types`. Кнопок нет при пустом `bot_username`, пустом
  списке или переопределённом `buttonRows()`. `BroadcastTypes` — реестр: `instance()/contains()/options()/label()/
  badgeColor()`; неизвестный токен fail-fast при создании и мягкий fallback (сырое значение/серый) в отображении.
  Лимиты глобальные и к типам не относятся: размер текста — 4000 (лимит API MAX), размер медиавложений —
  `image.max_kb`.
- **Согласие (opt-in)**: действие «Запросить согласие» на `ListBroadcasts` (под `permissions.create`) →
  `ConsentRequestService::sendRequest()`: кандидаты — активные чаты без записи в `max_broadcast_consents` для сегмента
  «Новости и акции» (`consent.segment_name`), `SendConsentRequestsJob` (лок, батчи) шлёт фиксированное сообщение с
  callback-кнопками «Согласен»/«Не согласен» (`ConsentPoll`, payload `consent:<action>`). Ответы ловит
  `HandleConsentCallback` (событие `MaxUpdateReceived`, регистрация в `FilamentMaxBroadcastsServiceProvider::boot()`),
  через `ConsentService` делает upsert факта и добавляет/убирает `chat_id` в сегменте согласий, после чего убирает кнопки
  из исходного сообщения (`editMessage`), отвечает на нажатие `ApiClient::sendAnswer` и подтверждает выбор в чат. Одна
  актуальная запись на пару `(segment_id, chat_id)`; `answeredChatIds()` — дедуп ответивших (их повторно не опрашивают,
  проигнорировавших — да). Журнал ответов — `BroadcastConsentResource` (read-only: `canCreate()`/`canEdit()` → `false`).
- **Таблицы**: `max_broadcasts`, `max_broadcast_recipients`, `max_broadcast_attachments`, `max_broadcast_segments`,
  `max_broadcast_consents` и pivot `max_broadcast_segment` (структура — наследие `broadcasts`/`broadcast_recipients` из
  хоста, но с префиксом `max_` во избежание конфликтов; вложения — отдельная таблица по несколько на рассылку). FK
  `created_by` → таблица `users` (модель — `config('filament-max-broadcasts.user_model')`). Миграции грузятся из пакета
  автоматически, шесть файлов; правило «не переименовывать и не править на месте» — в §2.
- **Переопределение моделей**: `broadcast_model`/`recipient_model`/`segment_model`/`consent.consent_model`/
  `chats_model`/`user_model` — конфигурируемы. В тестах для атрибутов вендорной `MaxChat` используется
  `getAttribute()` + локальный `@var`: у модели `laravel-max-client` нет `@property`, PHPStan level max иначе ругается.

### Соглашения

| Принцип            | Применение в этом пакете                                                                                                                                                                                                                                    |
|--------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| PHP 8.4 / strict   | `declare(strict_types=1)` во всех файлах, PSR-12, PHPStan **level max** (Larastan), namespace `GeekCo\FilamentMaxBroadcasts`                                                                                                                                     |
| SOLID / DRY / KISS | Тонкие Filament-страницы (вызов сервисов), логика — в сервисах/job, отправка — только в `BroadcastSender`, типы рассылок — в своих классах                                                                                                                          |
| TDD                | Новый код покрыт тестами: unit — сервисы/sanitizer/enums/models/job, feature — Filament-ресурсы через Testbench + фикстуры (`tests/Fixtures`: панель, TestUser, Gate)                                                                                                     |
| Тестовые границы   | `BroadcastSender` мокается через `$this->mock()`; `final`-сервисы `laravel-max-client` мокать нельзя — подменяй транспорт PSR-18 `ClientInterface`. `Queue::fake()` в `TestCase` обязателен: без него джобы уйдут в реальный API MAX                                                                 |
| BC-совместимость   | В patch-релизе не меняются публичные сигнатуры, ключи config, имена lang-строк и значения по умолчанию config; ломающие изменения — только в minor/major с записью в release-notes. Тексты подписей править можно, имена миграций и их тела — нельзя (§2) |
| Production-grade   | Fail-closed права, безопасные дефолты конфига, никаких секретов в коде/логах, ошибки MAX видны пользователю и логируются без чувствительных данных                                                                                                                                 |
| Локализация        | Имена — английские; русские тексты — в `lang/ru` и тестах; enum-подписи через `->label()`, без хардкода в представлениях; каждый новый ключ — сразу в `ru` и `en`                                                                                                     |

## 6. Локальная разработка

PHP/Composer на хосте не требуются — всё через Docker:

```bash
docker compose up -d --build
docker compose run --rm app composer install
docker compose exec -T app composer lint           # php-cs-fixer --dry-run
docker compose exec -T app composer format         # php-cs-fixer fix
docker compose exec -T app composer analyse        # PHPStan level max
docker compose exec -T app composer test           # PHPUnit
docker compose exec -T app composer coverage        # PHPUnit + coverage gate
docker compose exec -T app composer security-audit # composer audit
```

Флаг `-T` у `docker compose exec` обязателен для неинтерактивных команд: без него вывод PHPUnit/PHPStan ломается при
перенаправлении и портит разбор результатов. Для разовой оболочки — `docker compose run --rm app bash`. В контейнере
Xdebug включён и отчёт покрытия строится без дополнительных флагов; в CI покрытие требует `coverage: xdebug` и
`XDEBUG_MODE=coverage` (уже заданы в workflow).

## 7. Обязательный Gate перед завершением задачи

После изменений в `src/`, `tests/`, `config/`, `scripts/`, `database/`, `.github/`:

1. **Lint PHP**: `composer lint` (php-cs-fixer --dry-run) → 0 файлов с правками.
2. Если есть правки — `composer format`, затем повторить lint.
3. **Статика**: `composer analyse` (PHPStan level max) → 0 ошибок.
4. **Тесты**: `composer test` (PHPUnit) → зелёные (failOnRisky/failOnWarning).
5. **Покрытие**: `composer coverage` → ≥порога строк (`scripts/check-coverage.php`). **Порог 95%** — измеренный факт
   на текущем состоянии кода 97.84% строк (91.35% методов) на форме реестра 1.2 и 96.28% строк (89.73% методов) на
   legacy 1.1.x, то есть запас ~1.3 процентного пункта на худшей форме. Это храповик: при
   добавлении тестов порог можно поднимать, понижать его вниз, чтобы «покрасить» Gate, нельзя (gotcha 18).
6. **Audit**: `composer security-audit` → 0 уязвимостей.
7. **Проверка в условиях CI**: тот же Gate целиком в чистой копии дерева без `vendor/` и `composer.lock`, со свежей
   установкой по `composer.json` (правило 15, gotcha 11). Локальный прогон не заменяет этот шаг: локальные
   `vendor/` и `composer.lock` скрывают дрейф версий, а подмена содержимого `vendor/` вручную делает зелёный результат
   недостоверным.

Все шаги обязательны. Недоступный шаг — честно в отчёт. Отчёт по Gate пишется в файл сессии (4.1): команды и их
результаты, без приписывания того, что не запускалось.

## 8. OWASP Top 10 (обязательно при написании кода)

- **A01** — доступ к ресурсу и действиям только по правам (`permissions.*`); fail-closed, без прав — недоступно;
  `canCreate()`/`canEdit()` у read-only ресурса возвращают `false` (в Filament это не поведение по умолчанию).
- **A02** — секреты только в env; не логировать токены. Вложения рассылки по умолчанию кладутся на диск `public`
  (`image.disk`), то есть доступны по HTTP; отправка в MAX идёт по локальному пути, поэтому публичность диска не нужна —
  переход на приватный диск и его последствия решаются отдельной задачей (пункт 3 плана), молча не меняй дефолт.
- **A03** — текст рассылки санитизируется `BroadcastTextSanitizer` (whitelist, безопасные схемы href) до сохранения и
  до отправки; Blade/формы экранируются по умолчанию.
- **A04** — получатели не контролируются пользователем из формы (ветка выбора чатов — серверная логика, в UI только
  найденные сервером `chat_id`); лимиты вложений (`image.max_kb`, `image.accepted_mime_types`), лимит текста (4000).
- **A05** — publishable-конфиг с безопасными дефолтами; секреты не в коде; `env()` читается только в конфиге.
- **A06** — `composer security-audit` в Gate и CI; `composer.lock` не коммитится, поэтому дрейф версий проверяй честно
  (gotcha 11).
- **A07** — права строкой через `$user->can(...)` (spatie/Gate); постоянновременные сравнения — зона ответственности
  laravel-max-client.
- **A08** — загрузка вложений не доверяет имени файла и MIME: whitelist `image.accepted_mime_types`, проверка размера,
  отправка в MAX только через `uploadMedia` с чтением по локальному пути; наружу (в MAX) уходит только то, что
  пережило санитизацию и валидацию формы.
- **A09** — ошибки отправки в MAX логируются без чувствительных данных (`failed()` в джобах, catch в
  `BroadcastSender`, лог ошибок согласия); исключения не глушатся молча, пользователь видит уведомление.

## 9. Источник истины (MAX API)

- Спецификация: `https://github.com/geekcodev/max-openapi` (OpenAPI 3.1), сервер `https://platform-api2.max.ru`.
- Сводка эндпоинтов, DTO и enums: `max-php-client/docs/api-reference.md` в соседнем репозитории — не дублируй её
  здесь, читай.
- Отправка рассылки: `ApiClient::sendMessage` (`NewMessageBody`, `TextFormat::Html`), `uploadMedia` для медиавложений,
  `InlineKeyboardButton`/`ButtonType::Link` для кнопок-диплинок, `sendAnswer` для ответа на callback, `editMessage` для
  правки сообщения с кнопками. Сигнатуры брать из пакета `geekcodev/max-php-client`, не выдумывать.
- Реестр чатов/пользователей — из `geekcodev/laravel-max-client` (`MaxChat`, `MaxUser`, `MaxChatStatus`).
- Факты, влияющие на плагин: аутентификация — заголовок `Authorization: <token>` **без** `Bearer`; все timestamp API —
  Unix в **миллисекундах**; `chat_id`/`user_id` — int64 и могут быть **отрицательными** (группы и каналы); `message_id`/
  `callback_id` — строки; пагинация — `marker` + `count`; загрузка медиа идёт через `uploadMedia` и требует ожидания
  готовности вложения (ядро ретраит сам — не дублируй ретрай в джобах); rate limit отправки — 2/сек на чат плюс
  глобальный предохранитель 30 req/s (обеспечивается ядром, свой `sleep` в `SendBroadcastJob` не нужен и вредит).
- Прод-поведение важнее спеки в случаях, перечисленных в `docs/api-reference.md` ядра.

## 10. Частые ошибки (gotchas)

1. **Всё общение с реестром — через `Support\ChatRegistry`.** Constraint `^1.2.0` в `composer.json` закрывает legacy,
   но CI без `composer.lock` в любой момент может поставить другую форму реестра, поэтому вне адаптера нельзя
   обращаться к `maxUser`, `user_id` и `title` чата: на 1.1.x этого нет, а на 1.2 ключ строки — `chat_id`, не `id`.
   Проверка фактической версии: `composer show geekcodev/laravel-max-client` + §1 «Статус и версии».
2. **Уже выполненные миграции нельзя переименовывать и нельзя править на месте.** Правило §2 повторяет здесь gotcha
   намеренно: именно из-за сдвига имён (`000005 → 000006`, плюс забытая миграция про `recipient_chat_ids`) у хоста
   `php artisan migrate` упал бы на повторном `Schema::create`. Перед любым коммитом проверь `git status --short` и
   `git diff --cached --name-status` на миграциях: индекс не должен содержать файлов, которых нет в рабочем дереве.
3. **В тестах очередь подменена**: `Queue::fake()` в `TestCase`. Убери его — и `SendBroadcastJob` /
   `SendConsentRequestsJob` при `QUEUE_CONNECTION=sync` уйдут в реальный API MAX (упадут по сети или токену). Чтобы
   проверить сами джобы, вызывай `handle()` напрямую (job под моками `BroadcastSender`).
4. **Порядок provider'ов в Testbench**: Livewire подключай **последним** (`tests/TestCase.php`, уже так и сделано).
   Если Livewire зарегистрировать раньше Filament, `SupportServiceProvider` перебьёт биндинг хранилища состояния
   Livewire, и состояние теряется между вызовами — тесты падают странно. «Улучшать» порядок не надо.
5. **Filament v5, `counts()`** ждёт имя связи, а не колонки: `counts('recipients')`. Передача имени колонки или пустой
   вызов ничего не считает.
6. **Filament v5, поиск**: `->searchable()` больше не принимает `columns:` — передаётся массив `->searchable(['title'])`.
7. **Filament v5, ссылки**: у `TextEntry` внешняя ссылка — `->url(...)->openUrlInNewTab()`; `->openInNewTab()` не
   существует и даёт `BadMethodCallException`.
8. **Filament v5, подписи**: `TextEntry::make('name')` в состоянии `false` не рендерится — тест «видно подпись» на
   `false`-значении не проходит и вводит в заблуждение; проверяй значение атрибута, а не наличие метки.
9. **Filament, read-only ресурсы**: создание и редактирование разрешены по умолчанию. Для `BroadcastConsentResource`
   `canCreate()`/`canEdit()` обязаны возвращать `false`, иначе в UI появятся лишние кнопки.
10. **Кэш PHPStan врёт**: устаревший `.phpstan-cache` даёт ложные краши Larastan
    (`Undefined constant Larastan\Larastan\LARAVEL_VERSION`) — лечится `rm -rf .phpstan-cache`.
11. **`composer.lock` не коммитится — локальный Gate скрывает дрейф.** Локально стоит
    `geekcodev/laravel-max-client v1.1.1`, а CI (без lock) получает свежие версии: зелёное локально может быть красным в
    CI. Проверка в условиях CI: скопировать дерево без `vendor/` и `composer.lock` в `.ci-sim/`, выполнить там
    `composer install` и весь Gate — CI делает ровно то же. Каталог удаляется после проверки, в `.gitignore` не нужен.
    Отдельно опасна подмена содержимого `vendor/` вручную (своим бэкапом, распакованным архивом, выбранной версией
    пакета): `vendor/composer/installed.json` продолжает называть прежние версии, и зелёный Gate на таком наборе не
    говорит ничего о том, что поставит CI. Такой прогон годится как проверка совместимости с конкретной версией
    зависимости, но не закрывает Gate: закрывает только свежая установка по `composer.json` (правило 15, §7).
12. **Packagist из контейнера**: если `composer install` или `composer security-audit` зависает на сети (curl error 28,
    `Connection timed out`), помогает `COMPOSER_IPRESOLVE=4`
    (`docker compose run --rm -e COMPOSER_IPRESOLVE=4 app composer security-audit`); это временный флаг, в
    репозиторий его не коммитим. Ошибку `Cannot create cache directory /.cache/composer` результатом аудита не считать —
    контейнер работает от пользователя 1000:1000. Advisory API (`packagist.org/api/security-advisories/`) бывает
    недоступен, пока `repo.packagist.org` работает, поэтому audit и `composer update` запускай с парой повторов и
    результат «curl error 28» записывай как недоступный шаг, а не как «уязвимостей нет». В CI тот же таймаут
    роняет job: это сетевая флуктуация, а не уязвимость — перезапусти job и только потом разбирай список advisory.
13. **`docker compose exec` без `-T`** ломает пайпы и вывод PHPUnit/PHPStan — для неинтерактивных запусков добавляй
    `-T`.
14. **Покрытие падает незаметно**: `composer test` отчёт не строит. Гейт проверяет `composer coverage`; в CI нужен
    `coverage: xdebug` и `XDEBUG_MODE=coverage`.
15. **Лок джобы держится на кэше**: `Cache::lock("broadcast:{id}")` и `Cache::lock('consent:send-request')` координируют
    воркеры только на общем хранилище (redis/database/memcached). На `file`/`array` в продакшене рассылку отправят два
    процесса параллельно, а счётчики разъедутся. Требование к хосту, а не к пакету, — но проверяй конфиг перед релизом.
16. **Ассеты вендорной `MaxChat`**: у модели `laravel-max-client` нет `@property`, поэтому `status`, `chat_type`,
    `last_activity_at` читаются через `getAttribute()` с локальным `@var` (иначе PHPStan level max не проходит). Не
    «чини» это `@phpstan-ignore` в baseline — это маскировка production-ошибки.
17. **Только test-only записи в baseline**: `phpstan-baseline.neon` допустим для хелперов Filament/Livewire
    (`assertCanSeeTableRecords`, `assertActionVisible` и подобные); production-ошибки в baseline не добавлять. В
    `phpstan.neon` стоит `reportUnmatchedIgnoredErrors: false`: в CI Filament/Livewire/Larastan новее, часть хелперных
    ошибок там просто не возникает, и незакрытая запись иначе роняет анализ при зелёном коде.
18. **Порог покрытия 95% — храповик, а не цель**: сейчас 97.84% строк (91.35% методов) на форме реестра 1.2 и 96.28%
    строк (89.73% методов) на legacy 1.1.x, то есть запас ~1.3 процентного пункта на худшей форме.
    Понижать порог вниз, чтобы «покрасить» Gate, нельзя. Непокрытыми остаются защитные ветки, которые без моков
    финальных классов недостижимы: `BroadcastTextSanitizer` (HTMLDocument бросает исключение, пустой body, `unwrap` без
    родителя), а также редкие ветки `BroadcastForm`, `SendConsentRequestsJob`, `BroadcastSegmentResource`,
    `SendBroadcastJob`, `ViewBroadcast`, `BroadcastsTable`, `CreateBroadcast` и резолвера в service provider.
19. **Ловушка репозитория**: release-notes и планы больше не создаются в корне (`RELEASE-vX.Y.Z.md`, `PLAN-*.md`) и
    рабочая память больше не в `.ai/` — только в `.agents/` (gotcha по путям из §2 и 4.1).
20. **PyYAML молча перезатирает дубликаты ключей**: `yaml.safe_load` на workflow с двумя `name:` в одном job вернёт
    корректный словарь и ошибку не покажет (GitHub Actions такую ошибку, наоборот, подсвечивает). При правке
    `.github/workflows/*.yml` проверяй строгим загрузчиком с поиском дубликатов или `actionlint`; в dev-контейнере
    `actionlint` и `yamllint` не установлены, поэтому проверка — глазами плюс строгий разбор.
21. **Стабы форм реестра в тестах не наследуют `MaxChat`**: перекрытие `maxUser()` в подклассе конфликтует с
    дженериком родителя в PHPStan level max (`method.childReturnType`, шаблон `TDeclaringModel` не ковариантен).
    Стабы форм (`LegacyChat`, `PivotChat`, `PivotlessChat`) расширяют обычный `Model`; хостовые подклассы вроде
    `ConfiguredChat` наследуют `MaxChat`, потому что этого требует контракт `chats_model`. Вне формы проверяй
    `method_exists()`, а не `instanceof`, иначе статика считает проверку избыточной на одной из форм.
22. **`.gitattributes`**: нужен паттерн `/.agents/**` — с завершающим слешем `git check-attr export-ignore` молча
    отдаёт `unspecified`.

## 11. Чек-лист перед завершением задачи

- [ ] Gate пройден целиком, включая проверку в условиях CI (§7, шаг 7): lint 0 файлов, PHPStan 0 ошибок, PHPUnit
      зелёные, покрытие ≥порога, audit чист.
- [ ] Новый код покрыт тестами (unit — сервисы/sanitizer/enums/models/job, feature — ресурсы, действия, права, отказ
      при отсутствии прав).
- [ ] Публичный API не сломан: сигнатуры, ключи конфига, ключи переводов и дефолты совместимы с patch-релизом; имена и
      тела миграций не тронуты.
- [ ] Проверки прав fail-closed: без нужного `permissions.*` ресурс недоступен, действие скрыто и отклоняется на сервере.
- [ ] Нет прямых вызовов `ApiClient` из ресурсов/страниц/действий; отправка — через `BroadcastSender`.
- [ ] Каждый новый ключ перевода присутствует и в `lang/ru`, и в `lang/en`; новая переменная — в `.env.example` и
      `config/filament-max-broadcasts.php`.
- [ ] Секретов нет в коде, логах, коммитах; `README.md`, `.env.example` и `AGENTS.md` синхронны с кодом.
- [ ] Обновлены `.agents/plans/PLAN-filament-max-broadcasts.md` и `.agents/journals/` (строка в `JOURNAL.md` + файл в
      `sessions/` по формату 4.1).
- [ ] Коммит/тег/push — только по явному запросу пользователя; иначе оставлено рабочее дерево и описан статус.
- [ ] Если просили краткий текст коммита: только head, на английском по Conventional Commits, собран по всему diff ветки
      (не по последнему действию), коммит не создан. Body — только если просили отдельно.