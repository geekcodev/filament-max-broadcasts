---
tags: [ segments, consents, badges, chat-selection, filament, gate, v1.1.2 ]
date: 2026-10-30
---

# Бейджи chat_id и user_id как в селектах

## Проблема

В селектах форм чат выводится цветным бейджем типа чата, именем и ID. В инфолисте сегмента получатели выводились простым
списком имён, а в таблице согласий `chat_id` и `user_id` были голыми числами (тип чата — отдельной колонкой).

## Решение

- `ChatSelectionField::labelFor()` разделён: бейдж типа вынесен в `typeBadge()`, вывод селекта не изменился. Добавлен
  `idBadge(int)` для ID-бейджа в таблицах.
- `BroadcastConsentsTable`: колонка `chat_id` выводит бейдж типа (если чат найден в реестре) и бейдж ID; новая колонка
  `user_id` выводит бейдж ID или прочерк. Отдельная колонка типа чата убрана, потому что тип уже виден в бейдже
  `chat_id`. Поиск по имени и сортировки не менялись.
- `BroadcastSegmentInfolist`: получатели выводятся одним HTML-блоком `labelFor()` через `<br>`. Список
  `listWithLineBreaks()` не подходит: `TextEntry` экранирует элементы списка, и HTML внутри них не рендерится.
  Неизвестный чат остаётся текстом «Чат :id».
- Lang: ключ `consent_table.user_id` добавлен в `ru` и `en`; неиспользуемый `consent_table.chat_type` удалён из обоих.

## Тесты

- `BroadcastSegmentResourceTest::testViewPageRendersRecipientsAsBadgesLikeSelect`: в инфолисте есть `fi-badge`, тип и
  `(ID: 11)`.
- `BroadcastSegmentConsentsRelationManagerTest::testChatAndUserIdsRenderAsBadges`: бейджи chat_id и user_id в таблице
  согласий сегмента.

## Нюансы

- В `TextEntry` `->html()` сохраняет inline-стили после санитизации Filament, поэтому бейджи рендерятся.
- `BroadcastRecipientsRelationManager` (получатели рассылки) тоже выводит `user_id` и `chat_id` голыми числами. В задачу
  не входил, но тот же хелпер применим, если понадобится.

## Gate

- `composer format` и `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 263 теста, 708 assertions, OK.
- `composer coverage` — 97.96% строк (1533/1565), 92.00% методов, порог 95%.
- `composer security-audit` — уязвимостей нет.
- Чистая копия `.ci-sim` без `vendor/` и `composer.lock` со свежим `composer install`: lint, PHPStan, PHPUnit, покрытие
  и audit зелёные. Каталог после проверки удалён.
