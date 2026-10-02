---
tags: [ recipients, segments, resolver ]
date: 2026-09-04
---

# Выбор получателей и сегменты

## Задача

Расширить функционал рассылок: дать возможность выбирать получателей (а не рассылать всегда всем активным чатам).

## Уточнения у пользователя

Нужно было уточнить требования, т.к. «выбор получателей» неоднозначен:

- **Способ выбора**: multi-select в форме (чекбоксы), а не только фильтры/сегменты-правила.
- **Хранение выборки**: сохранять в `Broadcast` + возможность изменить при «повторе».
- **Переиспользование**: да, сохранять выбранных получателей как именованный сегмент.
- **Реализация сегментов**: отдельный Filament-ресурс «Сегменты».
- **Связь**: поле выбора сегмента + ручная корректировка чекбоксами.

## Что сделано

### Новая сущность: сегмент получателей

- Миграция `0001_01_01_000004_create_max_broadcast_segments_table.php`: таблица `max_broadcast_segments`
  (`name`, `description` nullable, `chat_ids` JSON, `created_by` FK→users, timestamps, index по `name`).
- Модель `src/Models/BroadcastSegment.php`: `chat_ids` cast → array, accessor `chat_count`, relation `creator()`.
- Ресурс `src/Resources/BroadcastSegmentResource.php` + форма `BroadcastSegmentForm`, таблица
  `BroadcastSegmentsTable`, страницы `Create/Edit/ListBroadcastSegment`. Права те же, что у рассылок
  (view/create/manage). Редактирование/удаление — по `permissions.manage`.

### Изменение `max_broadcasts`

Миграция `0001_01_01_000005_add_segment_and_recipients_to_max_broadcasts_table.php`:
- `segment_id` (FK→max_broadcast_segments, nullOnDelete) — привязка к сегменту;
- `recipient_chat_ids` (JSON, nullable) — явно выбранные chat_id (для «повтора» и отображения).

Обновлены `Broadcast`: `#[Fillable]` + `segment_id`/`recipient_chat_ids`, cast `recipient_chat_ids` → array, relation
`segment()`, PHPDoc.

### Сервис

`BroadcastService::create(..., ?array $chatIds = null, ?BroadcastSegment $segment = null)`:
- приоритет резолва: явные `chatIds` → `segment.chat_ids` → все активные чаты (`$resolver->resolve()`);
- пустой `chatIds` ([]) трактуется как «все активные» и сохраняется как `recipient_chat_ids = null`;
- при явном выборе сохраняется `recipient_chat_ids` для повторного использования в «повторе».

### UI

`BroadcastForm`:
- секция «Получатели» (hidden on view): `Select::make('segment_id')` (опции из `BroadcastSegment`),
  `->live()->afterStateUpdated()` заполняет `recipient_chat_ids` чатами сегмента; `CheckboxList::make('recipient_chat_ids')`
  со всеми активными чатами (поиск, bulk-toggle). Обе невидимы на view.
- stats-секция (view): добавлена строка «Группа получателей» — имя сегмента или «Все активные чаты».

`CreateBroadcast`: прокидывает `chatIds` (пустой → null) и `segment`.

Repeat-actions (`ViewBroadcast`, `BroadcastsTable`): передают `chatIds: $record->recipient_chat_ids` и
`segment: $record->segment` — повтор сохраняет ту же аудиторию.

Плагин: регистрирует оба ресурса; добавлен метод `segmentResource()` для переопределения.

### Правки под PHPStan level max

Модель `MaxChat` из laravel-max-client не объявляет `@property`, поэтому `getAttribute()` возвращает `mixed`.
Использованы локальные `@var int`/`@var string` при доступе к атрибутам (в `BroadcastService::resolveChats`,
`BroadcastForm::chatOptions`, `BroadcastSegmentForm`), чтобы удовлетворить `cast.*`/`return.type` правила без изменения
vendor-модели.

### Тесты

- `tests/Unit/Services/BroadcastServiceTest`: +4 теста (явные chatIds фильтруют; пустые — всем; сегмент;
  явные поверх сегмента).
- `tests/Unit/Models/BroadcastSegmentTest`: новый (создание, chat_count, creator).
- `tests/Feature/Resources/BroadcastSegmentResourceTest`: новый (запрет без прав, доступ, создание сегмента,
  создание рассылки с получателями).

## Решения/отклонения

- Сегмент — самостоятельная сущность с CRUD-ресурсом (а не вложенный список), т.к. пользователь выбрал вариант
  «отдельный ресурс».
- Ручная корректировка поверх сегмента (вариант «Сегмент + ручная корректировка»).
- Пустая выборка получателей = «всем активным чатам» (защита от нулевой рассылки при edge-case).
- `.env.example` не менялся: новых `FILAMENT_MAX_BROADCASTS_*` env-переменных не добавлено (`segment_model` — прямая
  ссылка на класс в конфиге).

## Gate

- lint 0, format 0
- analyse (PHPStan level max) 0
- test 102 (287 assertions)
- audit 0 критичных
