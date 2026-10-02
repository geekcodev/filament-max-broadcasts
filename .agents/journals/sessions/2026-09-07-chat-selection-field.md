---
tags: [ ui, chat-selection, search ]
date: 2026-09-07
---

# Закрыт tradeoff: серверный поиск чатов вместо CheckboxList

Контекст: после аудита и production-grade правок v1.0.2 единственным остаточным tradeoff было заявлено: форма
получателей/сегментов грузит все активные чаты в память CheckboxList'ом на каждый рендер. По команде «поправь tradeoff»
реализован серверный поиск.

## Что сделано

Создан общий билдер `src/Support/ChatSelectionField.php`:

- `make(statePath, label, helperText, required)` → `Select::make(...)->multiple()->searchable()->options([])`
  с `getSearchResultsUsing` и `getOptionLabelsUsing`;
- `searchResults(string $search): array<int, string>` — активные чаты (статус `MaxChatStatus::Active`), `chat_id LIKE`,
  сортировка `last_activity_at DESC`, лимит `OPTION_LIMIT = 50`, дедуп `unique('chat_id')`;
- `optionLabels(array $values): array<int|string, string>` — метки для выбранных значений (активные чаты из БД),
  для отсутствующих в реестре значений — сырое значение (JS отрисовывает пилюли);
- метка `sprintf('%s (ID: %s)', $title, $chatId)`, где `$title = title ?? chat_id` (у пары `max_chats` laravel-max-client
  колонки `title` нет — это фолбэк для переопределяемых `chats_model`).

Заменены оба места:

- `BroadcastSegmentForm` — CheckboxList `chat_ids` → `ChatSelectionField::make(...)` (required);
- `BroadcastForm` — CheckboxList получателей → `ChatSelectionField::make('recipient_chat_ids', ...)`; удалены
  `recipientsCheckboxList()` и `chatOptions()`; `segment_ids` + `afterStateUpdated` не тронуты.

Удалены неиспользуемые импорты (`CheckboxList`, `MaxChat`, `MaxChatStatus`) из обоих форм.

## Решения и отклонения

- **Поиск только по `chat_id`**: в схеме `max_chats` единственная гарантированная идентифицирующая колонка — `chat_id`;
  `title` в пакетной миграции laravel-max-client отсутствует (есть только `max_users.name`). «Серверно по title» требовало
  бы schema-интроспекции на каждый кестрой или join к `max_users` — признано сложнее полезного (хост может переопределить
  `chats_model` с полем `title`, и оно учитывается в метке через `title ?? chat_id`).
- **PHPStan level max**: `(int)/array_map` на `mixed` не проходят (`cast.int`, `argument.type`, `return.type`). Обход —
  сужение `is_int($value) || is_string($value)` внутри цикла и `(string)`-каст уже суженного значения; `@param
  array<mixed> $values` на `optionLabels`. `MaxChatStatus::Inactive` не существует (enum имеет Active/Stopped/Removed) —
  в тесте использован `Stopped`.
- **Семантика state не менялась**: `chat_ids`/`recipient_chat_ids` остаются массивами; feature-тесты (`fillForm` с
  массивами, `test...recipient_chat_ids`) не тронуты.
- **Фидерв API проверен по vendor**: `getSearchResultsUsing` получает named-параметр `search` (и query/searchQuery),
  `getOptionLabelsUsing` — `values` (fn → `getState()`); пустые label Filament заменяет на сырое значение (`$withDefaults`)
  — фолбэк согласован с этим.

## Документация

- `AGENTS.md`: в дереве раздела 4 добавлен `Support/ChatSelectionField.php`; раздел 5 — «ручная корректировка поиском
  активных чатов (серверно, только найденные)».
- `README.md`: пункт фич (чекбоксы → серверный поиск по `chat_id`) и раздел «Приём получателей».

## Тесты и Gate

Новый `tests/Unit/Support/ChatSelectionFieldTest.php` (4 теста):

- поиск возвращает только совпадающие активные чаты (`11`→[11], `22`→[22], `33` stopped→[], `absent`→[]);
- дедуп по `chat_id` (два ряда на один chat_id → один);
- `optionLabels` резолвит активные чаты (метка `'11 (ID: 11)'`) и фолбэк для отсутствующего (`999`→`'999'`);
- пустой выбор → `[]`.

Итог: **148 тестов / 410 assertions**, Gate: lint 0, analyse 0, audit 0 критичных.

PHPUnit: OK (148 tests, 410 assertions), время ~28 с.