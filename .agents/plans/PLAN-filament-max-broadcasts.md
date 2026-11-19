# План: filament-max-broadcasts (технический долг и развитие)

> Статус: **выполнен** 2026-11-18. Все четыре пункта закрыты. Работы по релизу v2.0.0 (ожидание тегов ядра и адаптера,
> серверная проверка вебхуков, повторный Gate в CI) ведутся по журналу `2026-11-17-core-v2-sync.md` и release notes
> v2.0.0, а не этим планом.

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

## 3. Приватный диск вложений `(готово)`

Закрыто 2026-11-18. Вложения рассылки нужны только для отправки: `BroadcastSender` читает файл по локальному пути и
заливает в MAX через `uploadMedia`, а получателям файлы отдают серверы MAX. Поэтому публичный HTTP-доступ к диску не
нужен, и дефолт `image.disk` переведён с `public` на `local` (`storage/app/private`).

1. Что зависело от публичности: только админка — превью картинок и ссылки на файлы на `ViewBroadcast`. Отправка,
   `BroadcastSender` и `FileUpload` от публичного URL не зависят.
2. Решение: приватный диск по умолчанию, в админке — временные подписанные ссылки на 30 минут. Логика собрана в
   `Support\AttachmentStorage` (`disk()`, `isPublicDisk()`, `url()`): публичный диск отдаёт обычный URL, приватный —
   `temporaryUrl()`, при отсутствии файла или поддержки временных ссылок возвращается null и в UI остаётся имя файла.
   Публичный диск остаётся рабочим вариантом хоста, но дефолт на него не возвращается.
3. `image.accepted_mime_types` проверен: там уже были видео. Для поля «Файлы» добавлен отдельный белый список
   `image.accepted_file_types` (pdf, doc (x), xls (x), ppt (x), txt, csv, zip) — раньше принимался файл любого типа.
   Картинки дополнительно ограничены MIME из конфига.
4. Тесты: отправка вложения с приватного диска (`BroadcastSenderTest`), ссылки и превью на странице просмотра
   (`BroadcastResourceTest`), хелпер `AttachmentStorage` (публичный/приватный диск, отсутствие файла, диск без временных
   ссылок), поле «Файлы» — конфигурируемый белый список и приём pdf через форму.

Критерий готовности выполнен: дефолт приватный, поведение зафиксировано в README и `AGENTS.md` (gotcha 29), покрыто
тестами.

---

## 4. Синхронизация README и документации `(готово)`

Проверено 2026-11-18: расхождений между `.env.example`, `config/filament-max-broadcasts.php`, `README.md` и
`AGENTS.md` нет.

1. README: команда `composer coverage` с порогом 95% (строка 233), переменные из `.env.example`, требование очереди и
   общего кэша для локов джоб (gotcha 15) — на месте.
2. Caveat про реестр чатов 1.1.x против 1.2, различие строк, значение для групп/каналов и `composer show` — есть.
3. Сверка `.env.example`, конфига, README и `AGENTS.md` выполнена вместе с задачей 3: добавлены `image.disk = local`,
   `image.accepted_file_types`, раздел «Вложения и приватность» в README и gotcha 29.

Критерий готовности достигнут.

---

## Готово

- (2026-11-18) Закрыт план целиком. Вложения рассылки по умолчанию лежат на приватном диске `local` и видны только в
  админке по временным подписанным ссылкам (`Support\AttachmentStorage`); для поля «Файлы» добавлен белый список
  `image.accepted_file_types`. README, `.env.example`, `AGENTS.md` (A02, A04, gotcha 29) и release notes v2.0.0
  синхронизированы. Журнал: `.agents/journals/sessions/2026-11-18-private-attachments-disk.md`. Gate: lint 0/107,
  PHPStan max 0, 316 тестов / 863 утверждения, покрытие 97.18% строк, audit 0; повторено в чистой копии без `vendor/`
  и `composer.lock`.
- (2026-11-02) Завершён блок согласия v1.1.2: legacy-payload `consent:<action>` удалён, слушатель принимает только
  `consent:<segmentId>:<action>`; после ответа сообщение с опросом удаляется (`deleteMessage`), при ошибке кнопки
  убираются правкой. `ConsentPoll::buttonRows()` больше не строит кнопки без сегмента. Журнал:
  `.agents/journals/sessions/2026-11-02-consent-delete-message.md`. Gate: 302 теста, покрытие 97.02% строк, чистая
  установка без lock.
- (2026-10-30) Кнопка «Создать» закрыта правом `broadcasts.create`: `ListBroadcastSegments` объявляет `CreateAction` в
  `getHeaderActions()` (в Filament v5 `ListRecords` её не добавляет), на `ListBroadcasts` добавлено явное
  `->authorize(...)` — иначе действие разрешено всем, кто вправе открыть список. План:
  `.agents/plans/2026-10-30-segment-create-action.md`. Gate: 251 тест, покрытие 97.92% строк, чистая установка без lock.
- (2026-10-30) Страница просмотра сегмента `BroadcastSegmentResource`: `ViewBroadcastSegment` + infolist со списком
  чатов и именами из `ChatRegistry`, действие «Просмотр» в таблице, `canView()` по `broadcasts.view`; правка/удаление на
  странице и в таблице — под `broadcasts.manage`. Gate: 245 тестов, покрытие 97.92% строк, включая чистую установку без
  lock.
- (2026-10-03) Миграции пакета переведены в полосу `0000_03`, позиция выведена из графа FK (`created_by` ссылается на
  `users`), guard `Schema::hasTable()` во всех шести create-миграциях, тест `tests/Feature/MigrationOrderTest.php`;
  релиз v1.1.1. Осталось перед релизом: Gate в условиях CI и `composer security-audit` (Packagist недоступен из
  контейнера).
- (2026-10-02) Рабочая память перенесена в `.agents/` (plans/release/journals/skills), `AGENTS.md` переработан,
  добавлены Gate покрытия, `.gitattributes`, единый CI-job; сброшен git-индекс с устаревшими именами миграций.