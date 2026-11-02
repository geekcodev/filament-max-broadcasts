---
tags: [ consents, journal, filter, migration, nullable, foreign-key, filament, gate, v1.1.2 ]
date: 2026-11-02
---

# Общий журнал согласий и сохранение истории при удалении сегмента

## Проблема

После переезда согласий в сегменты оставался общий read-only ресурс «Согласия» без фильтра по сегменту: найти ответы
конкретного сегмента можно было только глазами по всей таблице. При добавлении фильтра выяснилось, что удаление сегмента
с согласиями падает: `max_broadcast_consents.segment_id` объявлен `NOT NULL`, хотя внешний ключ создан с
`nullOnDelete()`. SQLite отклоняет запись NULL (`NOT NULL constraint failed`), а по документации MySQL `ON DELETE SET
NULL` требует nullable-колонку, поэтому на чистой установке MySQL таблица могла не создаться вовсе.

## Решение

- Общий ресурс `BroadcastConsentResource` оставлен: это единственное место, где видны согласия всех сегментов, и
  публичный API пакета (`->consentResource()`, маршрут `/broadcast-consents`). В `BroadcastConsentsTable` добавлен
  `SelectFilter` по сегменту, варианты — названия сегментов и «Без сегмента» (`none`, `whereNull('segment_id')`).
  Таблица на странице сегмента фильтр не получает.
- `max_broadcast_consents.segment_id` стал nullable. Для новых установок исправлена create-миграция
  `0000_03_000005` (она защищена `Schema::hasTable()`), для существующих добавлена ad-hoc миграция
  `0000_03_000008_make_broadcast_consent_segment_id_nullable`: guard по таблице, колонке и текущей nullable-ности, затем
  `change()`. `down()` удаляет записи с `segment_id = null` и возвращает `NOT NULL`.
- PHPDoc `BroadcastConsent::$segment_id` — `int|null`. Ключи `consent_table.filter_segment` и
  `consent_table.no_segment` добавлены в `lang/ru` и `lang/en`.
- Удаление сегмента больше не блокируется: согласия остаются в журнале с пустым сегментом и доступны фильтром «Без
  сегмента».

## Тесты

- `BroadcastConsentResourceTest`: фильтр по существующему сегменту; «Без сегмента» показывает запись удалённого сегмента
  и скрывает записи другого существующего сегмента.
- `BroadcastSegmentResourceTest::testDeletingSegmentKeepsConsentHistory`: удаление через header-действие сохраняет
  согласие с `segment_id = null`.
- `MakeConsentSegmentIdNullableTest`: миграция на таблице старой формы делает колонку nullable, после удаления сегмента
  согласие остаётся; повторный `up()` идемпотентен; `down()` удаляет orphan-записи и возвращает `NOT NULL`.

## Нюансы

- Правка уже выполненной create-миграции — осознанное исключение из правила §2 AGENTS.md: новые установки должны
  получать исправленную схему сразу, существующие приводит ad-hoc миграция. Исключение зафиксировано в §2 и gotcha 28.
- SQLite в тестах перестраивает таблицу при `change()`, но внешний ключ сохраняется: после `up()` удаление сегмента
  обнуляет `segment_id`. Проверено тестом на таблице старой формы.
- Записи удалённых сегментов не теряются, поэтому `down()` миграции при возврате `NOT NULL` вынужден их удалять.
- Сегмент, выбранный в форме и ставший пустым, по-прежнему не блокирует рассылку: она создаётся с нулём получателей,
  если других получателей нет.
- Временные отладочные тесты фильтра и удаления сегмента создавались в `tests/Feature/Tmp` и удалены.

## Gate

- `composer format` и `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 300 тестов, 814 assertions, OK.
- `composer coverage` — 97.34% строк (1720/1767), 89.50% методов, порог 95%.
- `composer security-audit` — уязвимостей нет (первый запрос упал на DNS, повторный чистый).
- Повторено в чистой копии без `vendor/` и `composer.lock` со свежим `composer install`: те же 300 тестов, покрытие
  97.34%, lint и PHPStan без ошибок, audit чист. Установлены `laravel-max-client` v1.2.3, `max-php-client` v1.1.11,
  Filament v5.11.0.
- Коммит и push не создавались.
