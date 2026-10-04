# План: filament-max-broadcasts (технический долг и развитие)

> Статус: **активный**. Задачи ниже переживают одну сессию и в порядке приоритета; после выполнения шаг
> помечается `(готово)` и дополняется журналом. Функциональность согласия, сегментов и типов рассылок
> закрыта, см. `PLAN-consent-callback.md` и `.agents/release/`.

---

## 1. Переход `laravel-max-client` на 1.2 и подъём constraint `(готово)`

Закрыто 2026-10-02 релизом v1.1.0: constraint `geekcodev/laravel-max-client` поднят до `^1.2.0`,
`geekcodev/max-php-client`
до `^1.1.8`. Gate зелёный на обеих формах реестра: 235 тестов, 97.84% строк на форме 1.2 (91.35% методов) и 96.28% строк
на legacy 1.1.1 (89.73% методов), PHPStan level max без ошибок на обеих.

Что сделано:

1. Форма реестра скрыта адаптером `Support\ChatRegistry`: связи чат-пользователь, ключ строки, `user_id`, `title` и
   отображаемое имя чата читаются только через него. Пять мест в `src/` переведены на адаптер: `ChatSelectionField`,
   `BroadcastConsentsTable`, `BroadcastRecipientsRelationManager`, `BroadcastService`, `SendConsentRequestsJob`.
2. Тестовая фикстура `tests/Fixtures/Chats.php` определяет форму по `Schema::hasColumn('max_chats', 'user_id')` и
   создаёт одинаковые данные на 1.1 и 1.2; `RawChat`/`RawUser` дают атрибуты в обход приведений и `$fillable` ядра.
3. `tests/Unit/Support/ChatRegistryTest.php` (38 тестов) закрывает обе формы: стабы форм, обе связи, поиск по имени,
   `displayName`, приведение `chat_id`/`user_id`, обработку нечисловых значений.
4. Новая ad-hoc миграция `0000_03_000007_make_broadcast_recipient_ids_signed` (названа так после перевода пакета в
   полосу `0000_03`, до него была `2026_10_02_000001`) переводит `max_broadcast_recipients.chat_id`/`user_id` в знаковый
   `bigint` через `Schema::table()->change()`, то есть одинаково на MySQL и PostgreSQL (на SQLite целые не знаковые и
   тип не меняется); тест `testStoresNegativeGroupChatId` пишет отрицательные идентификаторы группы. Историческая
   create-миграция получателей не тронута.
5. `ChatSelectionField::optionLabels()` теперь отбрасывает нескалярные значения до запроса, а не внутри цикла
   подстановки: массив с объектом больше не роняет `whereIn`.
6. Документация: требования и порядок апгрейда в README, §1 и gotcha 1/18/21 в `AGENTS.md`, структура миграций в §4.

Критерий готовности достигнут: зелёный Gate на форме 1.2, constraint в `composer.json` = `^1.2.0`, инструкция обновления
(сначала `php artisan migrate`, затем `php artisan max:upgrade`) в README.

Открытый остаток: `composer security-audit` и чистый `composer install` без lock выполнить не удалось — на момент сессии
`repo.packagist.org` недоступен из контейнера (`curl error 28`). Проверить в условиях CI обязательно: без lock
зависимости разрешатся свежими, и дрейф версий должен подтвердиться зелёным Gate (gotcha 11).

---

## 2. Покрытие до 95% `(готово)`

Закрыто 2026-10-02: покрытие строк 97.95% (1388/1417), методов 91.91% (159/173), порог поднят до 95% в
`scripts/check-coverage.php`. Тесты 194 (было 159). Порог — храповик, а не цель (gotcha 18).

Непокрытые точки, которые видно по отчёту:

Что сделано:

1. `BroadcastRecipientsRelationManager` — `tests/Feature/Resources/BroadcastRecipientsRelationManagerTest.php`:
   рендер таблицы получателей на `ViewBroadcast` (Livewire-тест relation manager), подпись колонки имени с откатом на
   анонимный label, поиск по имени пользователя, фильтр по статусу, подпись статуса.
2. `EditBroadcastSegment` — тесты правки в `BroadcastSegmentResourceTest`: имя/`chat_ids`/пересчёт `chat_count`, пустое
   описание, header-действие удаления, доступ к странице правки с правом и без.
3. `FilamentMaxBroadcastsPlugin` — `tests/Unit/FilamentMaxBroadcastsPluginTest.php`: id, регистрация ресурсов панели,
   переопределение через `->resource()`/`->segmentResource()`/`->consentResource()`.
4. `HandleConsentCallback` — в `HandleConsentCallbackTest`: update без `callback`, callback без `chat_id`, ошибки
   `editMessage`/`sendMessage`/`sendAnswer` (согласие фиксируется вопреки сбою любого из вызовов).
5. `ChatSelectionField` — ветки channel, `title`, `first/last/username` в `userDisplayName`, `chat_id` строкой и
   нечисловой, пропуск нескалярных значений в `optionLabels`.
6. `BroadcastConsentResource` — `tests/Unit/Resources/BroadcastConsentResourceMetadataTest.php`: подписи, навигация,
   read-only (`canCreate`/`canEdit`), `canDelete` по праву, пустая форма.
7. `BroadcastTextSanitizer` — удаление комментария верхнего уровня (`Body<!-- secret -->`); защитные ветки
   (`HTMLDocument` бросает исключение, пустой body, `unwrap` без родителя) остались непокрытыми: финальные классы ядра
   мокать нельзя, в плане они зафиксированы как ожидаемые.
8. Порог поднят до 95% в `scripts/check-coverage.php` (дефолт скрипта; `composer coverage` зовёт его без аргумента),
   факты и gotcha 18 обновлены в `AGENTS.md`, порог в команде README — тоже.

Критерий готовности: покрытие строк ≥ 95%, порог в конфиге обновлён, в release-notes — раздел «Качество».

Запас гейта теперь ~2.95 процентного пункта (97.95% при пороге 95%). Оговорка из пункта 1 остаётся в силе: CI разрешает
более свежие зависимости, чем локальный lock, поэтому чистую установку без lock всё ещё нужно проверять отдельно (gotcha
11).

---

## 3. Приватный диск вложений

`image.disk` по умолчанию `public`, то есть загруженные файлы рассылки доступны по HTTP (A02). `BroadcastSender`
читает файл по локальному пути `Storage::disk(...)->path(...)` и заливает в MAX через `uploadMedia`, поэтому публичность
хранилища не нужна технически.

1. Определить, что ещё зависит от публичности: превью вложений на `ViewBroadcast`, скачивание по ссылке в таблице,
   `Storage::url()` в UI.
2. Варианты: дефолт `local` с выдачей через временную подписанную ссылку Filament, либо сохранение текущего поведения с
   явным предупреждением в README. Решение принимает пользователь, дефолт молча не меняем.
3. Проверить `image.accepted_mime_types` (в текущем списке только картинки — стоит ли добавить видео/файлы).
4. Тест: вложение с приватного диска успешно отправляется в MAX, публичный URL отсутствует.

Критерий готовности: решение зафиксировано в плане и README, поведение покрыто тестом, дефолт соответствует решению.

---

## 4. Синхронизация README и документации

1. README: команда `composer coverage` (порог 95%), переменные окружения из `.env.example`, раздел про требование
   очереди и общего кэша для локов джоб (gotcha 15).
2. Caveat про реестр чатов: `laravel-max-client` 1.1.x против 1.2 (строка на пару против строки на чат), что это значит
   для групп и каналов и почему важен `composer show`.
3. Сверка `.env.example`, `config/filament-max-broadcasts.php`, `README.md` и `AGENTS.md` после каждой задачи этого
   плана.

Критерий готовности: расхождений между `.env.example`, конфигом и README нет; команды README совпадают с
`composer.json`.

---

## Готово

- (2026-10-03) Миграции пакета переведены в полосу `0000_03`, позиция выведена из графа FK (`created_by` ссылается на
  `users`), guard `Schema::hasTable()` во всех шести create-миграциях, тест `tests/Feature/MigrationOrderTest.php`;
  релиз v1.1.1. Осталось перед релизом: Gate в условиях CI и `composer security-audit` (Packagist недоступен из
  контейнера).
- (2026-10-02) Рабочая память перенесена в `.agents/` (plans/release/journals/skills), `AGENTS.md` переработан,
  добавлены Gate покрытия, `.gitattributes`, единый CI-job; сброшен git-индекс с устаревшими именами миграций.