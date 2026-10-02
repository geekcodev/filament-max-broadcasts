---
tags: [laravel-max-client, 1.2, ChatRegistry, migration, constraint, coverage, gate]
date: 2026-10-02
---

# Переход на форму реестра laravel-max-client 1.2

## Проблема

Constraint `geekcodev/laravel-max-client: ^1.1.0` был шире, чем нужно коду: локально стоял `v1.1.1` с формой реестра
«строка на пару пользователь-чат», а CI без `composer.lock` разрешал `1.2` с формой «строка на чат». Различия
затрагивали ключ строки (`id` против `chat_id`), наличие колонки `user_id`, связи чат-пользователь и колонку `title`.
Дополнительно `max_broadcast_recipients.chat_id`/`user_id` были объявлены `unsignedBigInteger`, то есть MySQL
отвергал бы отрицательные идентификаторы групп и каналов (на PostgreSQL беззнаковых типов нет, там `bigint` сразу
знаковый).

## Решение

Различия форм собраны в одном классе `Support\ChatRegistry`: `userRelation()`, `userRelations()`, `withUsers()`,
`whereUserLike()`, `user()`, `userId()`, `displayName()`, `chatId()`, `chatType()`, `userName()`. Форма определяется
по наличию метода `maxUser` у модели чата из `config('filament-max-broadcasts.chats_model')`, а не по версии пакета.
Пять мест в `src/` переведены на адаптер: `ChatSelectionField`, `BroadcastConsentsTable`,
`BroadcastRecipientsRelationManager`, `BroadcastService`, `SendConsentRequestsJob`.

Знаковые идентификаторы получателей решены новой ad-hoc миграцией `2026_10_02_000001_make_broadcast_recipient_ids_signed`:
`Schema::table()->change()` (MySQL и PostgreSQL), no-op на SQLite, где целые не знаковые. Первая версия миграции
держала сырой `ALTER TABLE ... MODIFY` ради MySQL и падала бы на PostgreSQL — хосты плагина работают и на нём.
Проверено на живых PostgreSQL 18 и MySQL 8: `bigint unsigned` становится `bigint`, комментарии колонок сохранены,
`up()` идемпотентен, отрицательный `chat_id` вставляется (до миграции MySQL отвечает `ERROR 1264 Out of range`).
Возврат в `down()` намеренно пустой: при наличии отрицательных идентификаторов беззнаковая версия неприменима.

Попутно исправлен `ChatSelectionField::optionLabels()`: нескалярные значения отбрасываются до `whereIn`, иначе объект
или вложенный массив в состоянии формы роняли запрос.

## Тесты

Тестовая фикстура `tests/Fixtures/Chats.php` определяет форму по `Schema::hasColumn('max_chats', 'user_id')` и создаёт
одинаковые данные на обеих версиях; `Chats::addUser()` пишет вторую строку реестра на 1.1 и запись в `max_chat_users`
на 1.2. `tests/Fixtures/RawChat.php` и `RawUser.php` задают атрибуты через `forceFill` и без приведений — так проверяются
`chat_id` числовой строкой и нечисловой, строковый `chat_type`, цепочки имени пользователя.

`tests/Unit/Support/ChatRegistryTest.php` (38 тестов) закрывает стабы обеих форм. Стабы форм (`LegacyChat`,
`PivotChat`, `PivotlessChat`) расширяют обычный `Model`: перекрытие `maxUser()` в подклассе `MaxChat` конфликтует с
дженериком родителя в PHPStan level max. `ConfiguredChat` наследует `MaxChat`, потому что этого требует контракт
`chats_model`.

Итог: 235 тестов, 610 assertions, один набор на обе формы реестра.

## Нюансы

Проверка формы в тестах обязана быть динамической (`method_exists`, `Schema::hasColumn`), а не через `instanceof`:
статика считает такую проверку избыточной на одной из форм и валидной на другой, и код перестаёт проходить level max
ни на одной версии ядра.

Признаки, по которым тест падал только на одной форме: `NOT NULL constraint failed: max_chats.user_id` (на 1.1
`user_id` обязателен), отсутствие `title` в `max_chats` и несовпадение ожидаемой связи (`maxUser` против
`chatUsers.maxUser`). Все три случая закрыты общими для форм фикстурами, а не ветвлением в тестах.

`ChatSelectionField` после перехода не должен дублировать адаптер: приватные `chatIdOf()` и `chatTypeValue()` были
идентичны `ChatRegistry::chatId()` и `ChatRegistry::chatType()` и удалены, источник модели чата читается через
`ChatRegistry::model()`. Иначе правило формы из §5 AGENTS.md размывалось бы вторым местом, знающим про реестр.

Тест отрицательного `chat_id` в наборе на SQLite знаковость не проверяет: SQLite целые знаковые по определению.
Ловит регрессию только MySQL — там вставка отрицательного идентификатора в беззнаковую колонку падает с `ERROR 1264`,
и на живой MySQL 8 миграция это проверена. В CI тест остаётся smoke-проверкой того, что данные проходят по модели.

`composer.lock` в репозитории не коммитится, поэтому локальные версии пакетов менялись подменой содержимого
`vendor/geekcodev/` (бэкапы обеих форм лежат в `/tmp/opencode/backup/`), а `installed.json` при этом продолжает
называть старую версию — сверять фактическую форму нужно по миграциям и моделям ядра, а не по `composer show`.

## Gate

Прогнан на двух наборах зависимостей:

- `laravel-max-client` 1.2.0 + `max-php-client` 1.1.8: `composer lint` — 0 файлов, `composer analyse` — 0 ошибок,
  `composer test` — 235 тестов / 610 assertions, `composer coverage` — 97.84% строк (1448/1480), 91.35% методов.
- `laravel-max-client` 1.1.1 + `max-php-client` 1.1.0: `composer lint` — 0 файлов, `composer analyse` — 0 ошибок,
  `composer test` — 235 тестов / 610 assertions, `composer coverage` — 96.28% строк (1425/1480), 89.73% методов.

`composer security-audit` выполнить не удалось: `repo.packagist.org` недоступен из контейнера
(`curl error 28`, `COMPOSER_IPRESOLVE=4` не помогает). Шаг 7 §7 (Gate в условиях CI) по той же причине не выполнен:
свежая установка по `composer.json` не проходит без сети. Локальные прогоны выше сделаны на подменённом вручную
`vendor/` из `/tmp/opencode/backup/`, поэтому они не закрывают Gate целиком — по решению сессии это теперь прямо
записано в правило 15, шаг 7 §7 и gotcha 11: подмена `vendor/` годится для проверки совместимости с конкретной
версией, но не как проверка того, что поставит CI. В этой сессии наборы были и свежими, и legacy, поэтому совместимость
проверена, а чистота установки по `composer.json` остаётся за CI.