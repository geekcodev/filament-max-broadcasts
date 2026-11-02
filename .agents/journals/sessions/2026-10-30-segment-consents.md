---
tags: [ segments, consents, relation-manager, filament, search, gate, v1.1.2 ]
date: 2026-10-30
---

# Согласия на рассылку на страницах сегмента

## Проблема

Согласия на рассылку (`max_broadcast_consents`) привязаны к сегменту через `segment_id`, но на страницах просмотра и
правки сегмента их не было видно: чтобы посмотреть, кто согласился по конкретному сегменту, нужно было уходить в журнал
«Согласия» и фильтровать его вручную.

Попутно выяснилось, что поиск по колонке «Имя» в журнале согласий падает: колонка вычисляется через `getStateUsing`, но
`searchable()` без аргумента ищет по имени колонки `name`, а такого столбца в `max_broadcast_consents` нет.

## Решение

- `BroadcastSegment::consents()` — `HasMany` по `segment_id`.
- `Resources\RelationManagers\BroadcastConsentsRelationManager` (`$relationship = 'consents'`) подключён в
  `BroadcastSegmentResource::getRelations()`, поэтому появляется и на `ViewBroadcastSegment`, и на
  `EditBroadcastSegment`.
- `BroadcastConsentsTable` получил общий `apply()` с флагом `withSegment`: `configure()` для журнала (с колонкой
  сегмента) и `configureForSegment()` для менеджера (без неё). Колонки, фильтры, права на удаление и eager-загрузка
  остаются в одном месте.
- Поиск по «Имени» переписан: `orWhereHas('chat', ...)` с поиском по имени пользователя чата через
  `ChatRegistry::whereUserLike()`. Условие пользователя вложено в группу `where(...)`, иначе `or` из
  `whereUserLike()` склеивается с условием связи, и поиск возвращает все согласия с существующим чатом.

## Тесты

`tests/Feature/Resources/BroadcastSegmentConsentsRelationManagerTest.php` (10 тестов): регистрация менеджера в ресурсе,
локализованный заголовок, изоляция по сегменту (чужие согласия не видны), отсутствие колонки сегмента, подпись для
неизвестного чата, поиск по имени собеседника (с проверкой, что чужой чат не попадает), видимость удаления при
`broadcasts.manage` и скрытие без него, наличие менеджера на страницах просмотра и правки.

Тест поиска сначала упал: найденное согласие не попадало в выборку. Отладка SQL показала группировку `or` внутри
подзапроса связи; после вложения в группу тест прошёл.

## Нюансы

- Поиск по имени в `BroadcastRecipientsRelationManager` уже был корректным: там условия идут по `maxChat.*` напрямую.
  Ошибка была только в журнале согласий и в новом менеджере, который переиспользует его таблицу.
- PHPStan потребовал аннотацию `Builder<MaxChat>` у аргумента `ChatRegistry::whereUserLike()` внутри замыкания поиска.
- Миграций и изменений конфига нет.

## Gate

- `composer format` и `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 261 тест, 699 assertions, OK.
- `composer coverage` — 97.94% строк (1525/1557), 91.88% методов, порог 95%.
- `composer security-audit` — уязвимостей нет.
- Чистая копия `.ci-sim` без `vendor/` и `composer.lock` со свежим `composer install`: lint, PHPStan, PHPUnit, покрытие
  и audit зелёные. Каталог после проверки удалён.
