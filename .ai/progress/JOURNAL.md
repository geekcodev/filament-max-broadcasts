# Журнал прогресса — filament-max-broadcasts

Единый журнал изменений и прогресса проекта. Краткое описание каждой сессии и её итоги — здесь; подробности — в
отдельных файлах каталога `sessions/` (по одному `.md` на сессию).

Формат записи (добавляй новые сверху):

```
## [YYYY-MM-DD] Краткое название
- Сделано: ...
- Решения/отклонения: ...
- Прогресс по чек-листу: ...
- Подробности: `sessions/YYYY-MM-DD-kratkoe-nazvanie.md`
```

---

## [2026-09-02] Мультимедиа-вложения: картинки + видео + файлы (таблица broadcast_attachments)

- Сделано: одиночный `image_path` на `max_broadcasts` заменён на отдельную таблицу
  `max_broadcast_attachments` (по несколько вложений: картинки, видео, файлы). Новая миграция
  `0001_01_01_000003_create_max_broadcast_attachments_table.php`, модель `BroadcastAttachment` (cast
  `upload_type→UploadType`,
  `broadcast()` BelongsTo), у `Broadcast` relation `attachments(): HasMany` (orderBy sort_order) и убрано поле
  `image_path`. Контракты: `BroadcastService::create(..., array $attachments = [], ...)` (валидация
  `UploadType::tryFrom` + непустой path, иначе `InvalidArgumentException 'Invalid broadcast attachment #%d.'`,
  сохранение `saveAttachments()` с sort_order=индекс);
  `BroadcastSender::send(Recipient, string $text, array $media, BroadcastTypeContract $type)` — `uploadMedia()` мапит
  `UploadType→AttachmentType` через match, несколько вложений в `attachments[]` `NewMessageBody`;
  `SendBroadcastJob::sendTo()`
  собирает `$media` из `attachments`. UI: `BroadcastForm` — три мульти `FileUpload` (`images`/`videos`/`files`),
  mime-типы и лимит из `image.accepted_mime_types`/`image.max_kb`, View — `RepeatableEntry` вложений; `CreateBroadcast`
  собирает attachments; repeat-actions (`ViewBroadcast`, `BroadcastsTable`) копируют вложения. Config/.env.example:
  параметры
  `accepted_mime_types`/`accepted_extensions`, `max_kb` дефолт = 51200 КБ (лимит MAX 50 МБ, корректные единицы для
  FileUpload::maxSize).
- Решения: вложения — отдельная сущность с FK (а не колонка-коллекция), upload_type строкой/16 (значения из
  max-php-client `UploadType`), валидация на создании и мягкий skip в sender; форма хранит пути в 3 ключах и сборка в
  `handleRecordCreation`.
- Gate: lint 0, format 0, analyse 0 (baseline пересобран на 76 ошибок в тестах), test 80 (228 assertions), audit 0
  критичных.
- Подробности: `sessions/2026-09-02-multimedia-vlozheniya.md`

## [2026-09-03] Типы рассылок: убраны лимиты из BroadcastTypeContract

- Сделано: `maxTextLength()`/`imageMaxKb()` удалены из `BroadcastTypeContract`, трейта `BroadcastTypeDefaults` и реестра
  `BroadcastTypes` (YAGNI — не варьируются по типу: текст 4000 = константа API MAX, фото = `image.max_kb` из конфига).
  Форма: `maxLength(4000)` и `maxSize(config image.max_kb)` статично, убран `->live()` на select и helper
  `selectedType()`
  (импорт `Get`). Лимиты остались глобальными. Синхронизированы README/AGENTS.md/config/.env.example; сессия дополнена.
- Решения: верный аргумент пользователя — per-type лимиты это лишняя абстракция; контракт сокращён до реальных отличий
  поведения типов (подписи/кнопки/цвет).
- Gate: lint 0, analyse 0, test 77 (205 assertions), audit 0 критичных.

## [2026-09-03] Типы рассылок → BroadcastTypeContract (типы как поведение)

- Сделано (эволюция подхода от 2026-09-02): вместо конфиг-реестра строк + resolver-слоя кнопок введён интерфейс
  `Contracts\BroadcastTypeContract` (fromToken/label/buttonRows/badgeColor/maxTextLength/imageMaxKb) и полиморфные
  backed-enum'ы на тип: `Enums\BroadcastTypes\{News,Promo}` (case value === токен) + трейт дефолтов
  `Support\BroadcastTypeDefaults` (подпись из lang, кнопки из `bot_username` + `buttons.per_type.<token>`, badge `gray`,
  лимиты). Удалены `Enums\BroadcastType.php`, `Support\BroadcastButtonsResolver.php`,
  `Support\DefaultBroadcastButtonsResolver.php`. Реестр `Support\BroadcastTypes` переписан под token→class:
  `instance()/contains()/options()/label()/badgeColor()/лимиты`, неизвестный токен — fail-fast при создании и мягкий
  fallback (сырой токен/серый/дефолтные лимиты) в отображении. `BroadcastSender` принимает `BroadcastTypeContract`
  (кнопки из `$type->buttonRows()`), `Test/Fixtures/OffersType` — кастомный тип хоста на трейте. Форма: динамические
  лимиты текста/фото по типу (`->live()` + `Get`), колонка: badgeColor из типа. Синхронизированы README, AGENTS.md,
  config, .env.example.
- Решения: php-enum'ы не наследуются, но реализуют интерфейсы — поэтому каждый тип = свой enum с контрактом; релиза
  v1.0.0 ещё нет, поэтому схема конфига меняется сейчас бесплатно, а после релиза добавление метода в интерфейс будет
  BC-break (для хостов — реализовать в своём типе); интерфейс «узкий и полный» (permission ()/кастомный send-flow —
  YAGNI). Колонка `type` в БД осталась строкой. Дефолтные `label()/labels()` для статусов не трогали (закрытые
  множества). Baseline пересобран (54 ошибки, все — тесты; src/config/database чисты).
- Gate: lint 0, analyse 0, test 78 (209 assertions), audit 0 критичных.
- Подробности: `sessions/2026-09-02-universalnye-tipy-i-knopki.md` (рефактор в этом же файле сессии)

## [2026-09-02] Универсальные типы рассылок и кнопки per type

- Сделано: тип рассылки выведен из enum-каста в открытую строковую модель. Реестр типов в конфиге (`types`: string ⇒
  подпись, `null` → из lang `broadcasts.type.<type>`) + `Support\BroadcastTypes::options()/label()`. Кнопки-диплинки
  переведены со «только promo» на per-type: конфиг `buttons.{resolver, per_type}` (для типа кнопки берутся из
  `buttons.per_type.<type>`, кнопок нет при пустом `bot_username` или пустом списке), новый интерфейс
  `Support\BroadcastButtonsResolver::rows(string $type)` и дефолт `DefaultBroadcastButtonsResolver`; `PromoButtons`
  удалён. `BroadcastSender` принимает `string $type` и резолвера (без promo-gate), `BroadcastService::create()` и
  Filament (Form/Table/Create) работают со строками и `BroadcastTypes`; у `Broadcast` убран enum-каст `type`.
  ServiceProvider: биндинги `BroadcastSender` (2 аргумента) и `BroadcastButtonsResolver` по `buttons.resolver`.
  Обновлены lang `type_helper` (ru/en), README, AGENTS.md, .env.example. Тесты: +`BroadcastTypesTest`,
  `PromoButtonsTest` → `DefaultBroadcastButtonsResolverTest` (+кейсы «тип без конфигурации», bot_username), sender
  (+кастомный тип с кнопками, тип без кнопок), service/model/job на строки.
- Доводка (по ревью): у `BroadcastType` удалены ставшие мёртвыми `label()/labels()` (подписи — только `BroadcastTypes`,
  enum остался источником констант-дефолтов); комментарий колонки `type` в миграции приведён к реестровому смыслу
  (`config types`, по умолчанию news|promo); `BroadcastService::create()` валидирует тип через
  `BroadcastTypes::contains()` (fail-fast, `InvalidArgumentException`) — форма и так ограничивает выпадашкой.
- Решения/отклонения: enum `BroadcastType` сохранён как источник дефолта (`BroadcastType::News->value`); UI-подписи
  привязаны только к `BroadcastTypes`. Глубокая проверка структуры кнопок вынесена в resolver-тест (sender-тесты
  проверяют тип вложения). `phpstan-baseline.neon` пересобран (53 ошибки, все — тесты; `src/`/`config/`/`database/`
  чисты).
- Gate: lint 0, analyse 0, test 73 (196 assertions), audit 0 критичных.
- Подробности: `sessions/2026-09-02-universalnye-tipy-i-knopki.md`

## [2026-09-02] Ревизия RELEASE и журнала

- Сделано: `RELEASE-v1.0.0.md` ревизован — исправлены 2 неточности (permissions.send — зарезервировано, фактическая
  отправка под `broadcasts.manage`; тесты 67 → 68). По решению пользователя `RELEASE-*.md` добавлен в `.gitignore`
  (файл локальный, в git не пойдёт), соответствующая запись из журнала удалена.

## [2026-09-01] Мелкие улучшения конфига и миграций

- Сделано: (1) `promo_buttons` в конфиге — дефолт `[]` вместо хост-специфичных «Запись на сервис/Консультация» (пример
  добавлен закомментированным блоком; без `bot_username` кнопок не было и не будет); (2) миграции: DB-level
  `->comment()` на таблицы и колонки; (3) `error` в `max_broadcast_recipients`: `string(191)` → `text` — Postgres жёстко
  соблюдает varchar (191), а туда пишется `$exception->getMessage()` (длиннее 191) — иначе падал бы `save()` в catch и
  валил рассылку в `failed`. Сознательное отклонение от хоста ради универсальности (MySQL/PostgreSQL/SQLite). (4)
  ревизия всех миграций на production-grade: `status` получателей `8` → `16` (максимум сейчас `pending`=7, статус
  длиннее потребовал бы ALTER; плюс несогласовано с `max_broadcasts`, где 16). Итоговые отклонения от хоста: префикс
  `max_`, `error`→`text`, `status` 8→16, DB-comments.
- Gate: lint 0, analyse 0, test 68 (184 assertions) — зелёный.
- Подробности: `sessions/2026-09-01-melkie-uluchsheniya.md`

## [2026-09-01] GitHub CI для Gate (по образцу filament-max-chat)

- Сделано: создан `.github/workflows/ci.yml` — 4 джобы (lint/analyse/test/audit), полностью в стиле `filament-max-chat`:
  checkout@v4 + shivammathur/setup-php (PHP 8.4, composer:v2, coverage none) + actions/cache@v4 по `composer.lock` +
  `composer install` → `composer lint` / `composer analyse` / `composer test` / `composer audit`. Отличий два (целевых):
  в джобу test добавлены `extensions: dom, fileinfo, libxml` (требования `require` в composer.json);
  `composer.lock` в репо не коммитится (как и в `filament-max-chat`), поэтому в CI `hashFiles` пуст — кэш vendor
  работает через `restore-keys`, установка идёт со свежим резолвингом зависимостей (поведение идентично эталону).
- Gate прогонен после добавления workflow: lint 0, analyse 0, test 68 (184 assertions), audit 0 критичных.
- Статус по чек-листу: шаги 0–21 завершены; шаг 22 (коммит/публикация) — по явному запросу. CI — готовый артефакт к шагу
  22.
- Подробности: `sessions/2026-09-01-github-ci-dlya-gate.md`
- Сделано: проведён полный аудит реализации против AGENTS.md и эталона хоста (`/home/user/web/chisto-service-mini-app`
  `app/Services/BroadcastService`, `MaxBot::sendBroadcast`, `BroadcastTextSanitizer`, `Jobs/SendBroadcastJob`). Найдены
  и исправлены 2 реальных отклонения: (1) `BroadcastResource::canView()` — страница просмотра была доступна по прямому
  URL без права `broadcasts.view` (в не-strict хосте Filament без policy даёт implicit allow, нарушение A01); (2)
  `BroadcastRecipientsRelationManager` — захардкоженный `$title = 'Получатели'` заменён на static `getTitle()`
  через `__('filament-max-broadcasts::broadcasts.recipients.title')` (правило «подписи UI — через lang»). Добавлен
  регрессионный тест `testViewPageIsForbiddenWithoutViewPermission`. PHPStan-краш `Undefined constant LARAVEL_VERSION`
  на reused-кэше устранён переносом `tmpDir` в проектный `.phpstan-cache` (добавлен в `.gitignore`) — стартовый краш был
  из-за устаревшего `/tmp/phpstan` в контейнере.
- Сверка с эталоном: `SendBroadcastJob`, `BroadcastService`, `BroadcastTextSanitizer` и `BroadcastSender` — честный
  перенос логики хоста (улучшения: параметры очереди из конфига, guard `trim($imagePath)` в sender, configurable disk).
  К осознанным расхождениям/замечаниям отнесено: повторная загрузка фото на каждого получателя (так же в хосте — upload
  token не кэшируется), `Storage::disk()->path()` поддерживает только локальные диски (дефолт `public`),
  `appDeepLink` без urlencode (значения только из конфига).
- Прогресс по чек-листу: шаги 0–21 (§11 PLAN) — завершены; шаг 22 (коммит/публикация) — по явному запросу. Gate зелёный:
  `lint` 0, `analyse` 0, `test` 68 (184 assertions), `audit` 0 критичных.
- Подробности: `sessions/2026-09-01-audit-sootvetstviya-agents.md`

## [2026-09-01] Полный Gate и оформление релиза v1.0.0

- Сделано: прогнан полный Gate (§10). Исправлены найденные проблемы: (1) `FilamentMaxBroadcastsServiceProvider` —
  добавлен импорт `Illuminate\Container\Container` (ошибка PHPStan «unknown class Container»); (2) `ViewBroadcast` —
  действие удаления переделано на `Action::make('removeBroadcast')` с `requiresConfirmation` и лейблами из `lang/`
  (убран хардкод `'Delete'` и nullsafe `$record?->delete()`); (3) `tests/TestCase.php` — добавлен
  `PRAGMA foreign_keys = ON` для SQLite in-memory, без этого каскадное удаление не удаляло `BroadcastRecipient`
  (1 ошибка в `testDeleteActionRemovesBroadcast`). Сгенерирован `phpstan-baseline.neon` (59 ошибок, все — тестовые
  файлы; `src/`, `config/`, `database/` чисты на level max). Созданы `README.md` и `RELEASE-v1.0.0.md`.
- Решения: PHPStan-ошибки тестов закрыты baseline-файлом (подход чат-плагина с `ignoreErrors` не покрывал всех кейсов);
  `phpstan-baseline.neon` коммитится вместе с проектом. Каскадное удаление получателей оставлено на БД (FK
  `cascadeOnDelete` из миграции) — в тестах FK включены через PRAGMA.
- Прогресс по чек-листу: шаги 0–21 (§11 PLAN) — завершены. Остался шаг 22 (коммит/публикация — только по явному запросу
  пользователя). Gate зелёный: `lint` 0, `analyse` 0, `test` 67 (183 assertions), `audit` 0 критичных.
- Подробности: `sessions/2026-09-01-gate-i-reliz-v100.md`

## [2026-09-03] Превью вложений и ссылки на файлы на детальной странице

- Сделано: на детальной странице рассылки (`ViewBroadcast`) инпуты загрузки файлов скрыты на `view` (не несут
  функциональности просмотра), а раздел «Вложения» (`BroadcastForm::attachmentsList`) переписан: для изображений —
  превью миниатюры через `ImageEntry` (диск из конфига `image.disk`, высота 120, lazy), для остальных типов
  (видео/аудио/файлы) — кликабельная ссылка «тип — имя файла» через `TextEntry->html()` с `href` = URL файла
  (`Storage::disk()->url`). Если у изображения нет пути/файла — показывается ссылка-фолбэк. Хелперы:
  `attachmentPath()`, `attachmentFileUrl()`, `isImagePreviewable()`. Импорты `ImageEntry`, `UploadType`, `Storage`.
- Тесты: новый feature-тест `testViewPageShowsImagePreviewAndLinksForOtherFiles` — `Storage::fake('public')`, создаёт
  image + file вложения, проверяет `<img src="...">` и `href="..."` в HTML ответа страницы.
- Решения: превью делаем нативно через `ImageEntry->disk()` + storage-path (а не через готовый URL в `->state()`), т.к.
  `ImageEntry::getImageUrl()` отдаёт state как есть только для валидных URL, а относительный путь превратился бы в
  двойной `/storage/storage/...`. Взаимно-исключающая видимость Image/TextEntry по `upload_type`, с фолбэком на ссылку
  при недоступном изображении.
- Локализация: подпись типа в ссылке вложений (`upload_type->value` → lang) — добавлены
  `form.attachment_types.{image,video,audio,file}` в `lang/{ru,en}/broadcasts.php` и хелпер
  `attachmentTypeLabel()` в `BroadcastForm`.
- Gate: lint 0, analyse 0, test 91 (254 assertions, +1 тест), audit 0 критичных.
- Подробности: `sessions/2026-09-03-preview-vlozheniy-na-view.md`

## [2026-09-03] Чистка мёртвого кода + удаление план-файла

- Сделано: ревизия на соответствие AGENTS.md выявила мёртвые поля конфига — удалены `permissions.send`
  (`broadcasts.send`, не читалось: права только view/create/manage) и `image.accepted_extensions` (не читалось).
  Синхронизированы `.env.example`, `config/`, `README.md`, `AGENTS.md`, `tests/` (убраны флаг
  `can_send_broadcasts` у `TestUser`/миграции users, Gate `broadcasts.send`). Весь baseline PHPStan опустошён:
  `src/` и `tests/` чисты на level max (0 ошибок), baseline-файл оставлен с `ignoreErrors: []`. Финальный Gate зелёный:
  lint 0, analyse 0, test 90 (247 assertions), audit 0 критичных, покрытие 90.81% строк.
- Удалён `PLAN-filament-max-broadcasts.md` (был untracked, план полностью реализован в рамках объёма; эволюция кода —
  мультимедиа, BroadcastTypeContract, удалённый send — зафиксирована в журналах сессий и RELEASE). Ссылки на план
  остались только в исторических журналах `.ai/progress/` (намеренно).
- Решения: план удалён, т.к. выполнен; дальнейшее — только релиз/коммит по явному запросу пользователя (шаг 22 AGENTS).
- Подробности: `sessions/2026-09-03-chistka-i-udalenie-plana.md`

## [2026-08-31] Инициализация проекта и базовой документации

- Сделано: создан `AGENTS.md` (описание проекта, требования, Gate, OWASP, локальная разработка), заведён журнал
  прогресса `.ai/progress/`, оформлен рабочий план в `PLAN-filament-max-broadcasts.md` (не пойдёт в git, будет удалён).
- Решения: плагин самодостаточный, таблицы с префиксом `max_`, кнопки акций настраиваемые, права
  `broadcasts.view/create/send/manage`, без Blade-вьюх и без real-time-прогресса на текущем этапе. Проект чистый — без
  упоминаний внешних хост-проектов в git-файлах.
- Прогресс по чек-листу: 0 из 22 шагов (§11 PLAN) — реализация ещё не начата.
- Подробности: `sessions/2026-08-31-inicializaciya-i-dokumentaciya.md`