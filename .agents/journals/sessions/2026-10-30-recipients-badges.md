---
tags: [ recipients, badges, chat-selection, relation-manager, gate, v1.1.2 ]
date: 2026-10-30
---

# Бейджи в таблице получателей рассылки

## Проблема

После правки сегментов и согласий (`2026-10-30-segment-badges.md`) таблица получателей рассылки
(`BroadcastRecipientsRelationManager`) осталась единственным местом, где `chat_id` и `user_id` выводились голыми
числами.

## Решение

- Колонка `chat_id` выводит бейдж типа чата (если чат найден в реестре через `maxChat`) и бейдж ID через
  `ChatSelectionField::typeBadge()` и `idBadge()`, тот же приём, что в таблице согласий. Без `chat_id` — прочерк.
- Колонка `user_id` выводит бейдж ID через `ChatSelectionField::idBadge()`, без `user_id` — прочерк.
- Поиск по обеим колонкам сохранён: они реальные столбцы таблицы получателей.

## Тесты

`BroadcastRecipientsRelationManagerTest::testChatAndUserIdsRenderAsBadges`: в таблице есть `fi-badge`, тип чата,
`ID: 11` и `ID: 1`.

## Gate

- `composer format` и `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 264 теста, 713 assertions, OK.
- `composer coverage` — 97.91% строк (1547/1580), 91.54% методов, порог 95%.
- `composer security-audit` — уязвимостей нет.
- Чистая копия `.ci-sim` без `vendor/` и `composer.lock` со свежим `composer install`: lint, PHPStan, PHPUnit, покрытие
  и audit зелёные. Каталог после проверки удалён.
