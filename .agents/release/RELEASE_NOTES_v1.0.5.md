# v1.0.5

В таблицу согласий добавлены имя и тип чата — теперь видно, кто именно ответил.

## Что изменилось

**Согласия: имя и тип получателя.** Раньше ресурс «Согласия» показывал только числовой MAX `chat_id` — неясно, какому
получателю он соответствует. Добавлена связь `BroadcastConsent::chat()` (BelongsTo к реестру `max_chats` по `chat_id`,
по аналогии с `broadcast_recipients`), и таблица теперь выводит две новых колонки: **Тип** (бейдж
«Диалог»/«Группа»/«Канал» с цветом, как в выборе получателей) и **Имя** (для диалога — из профиля `max_users`, name →
first+last → username; для групп/каналов без названия — честный фолбэк на `chat_id`). Если чат отсутствует в реестре,
выводится «Чат :id». Колонка имени участвует в поиске. Связь eager-load'ится через `chat.maxUser`, так что рост числа
согласий не даёт N+1.

**Рефакторинг без дублирования.** Из `Support\ChatSelectionField` вынесены переиспользуемые статики `chatTypeLabel()` и
`chatTypeColor()`: решётка типов и их подписей с цветами учитывается в одном месте, и компонент выбора получателей, и
таблица согласий используют её (DRY).

## Изменения схемы и конфигурации

Схема БД и `config/filament-max-broadcasts.php` не менялись — новых переменных нет. Данные имени и типа берутся из уже
существующих таблиц `max_chats`/`max_users`. В `lang/{ru,en}` добавлены ключи таблицы согласий
`consent_table.chat_type`, `consent_table.name` и `consent_table.anonymous_chat`.

## Тесты

159 тестов (456 assertions). Добавлен тест листинга согласий с именем и типом чата
(`BroadcastConsentResourceTest::testIndexPageShowsChatNameAndType`). PHPStan level max — без ошибок, PSR-12,
`declare(strict_types=1)`. Gate:

1. `php-cs-fixer --dry-run` — 0.
2. PHPStan level max (Larastan) — 0 ошибок.
3. PHPUnit — 159 тестов / 456 assertions, зелёные (failOnRisky/failOnWarning).
4. `composer audit` — 0 критичных.

---

[Laravel](https://laravel.com) · [Filament](https://filamentphp.com) · [laravel-max-client](https://github.com/geekcodev/laravel-max-client) · [max-php-client](https://github.com/geekcodev/max-php-client)