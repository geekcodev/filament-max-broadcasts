---
tags: [ consent, ui, localization, empty-state, gate ]
date: 2026-11-20
---

# Локализация пустого состояния журнала согласий

## Проблема

На странице просмотра сегмента в таблице согласий без записей выводилось «Не найдено broadcast consents»: Filament
подставляет в заголовок пустого состояния `getPluralModelLabel()` таблицы, а для relation manager это производное от
класса модели английское «broadcast consents». Подпись не проходила через lang-файлы пакета.

## Решение

- `BroadcastConsentsTable` явно задаёт `emptyStateHeading()` и `emptyStateDescription()`; таблица общая, поэтому фикс
  действует и на странице сегмента, и в общем ресурсе «Согласия».
- Добавлены ключи `consent_table.empty_heading` («Согласий пока нет» / «No consents yet») и
  `consent_table.empty_description` («Отправьте запрос согласия из сегмента — здесь появятся запросы и ответы.») в
  `lang/ru` и `lang/en`.
- Дефолтное описание Filament показывается только при наличии create-действия, поэтому подсказка задана явно.

## Тесты

Добавлены проверки пустого состояния на странице сегмента и в общем журнале: видна локализованная подпись, сырого
«broadcast consents» нет. Итог: 357 тестов / 1034 утверждения.

## Нюансы

- Правка только на уровне таблицы: ресурс `BroadcastConsentResource` и relation manager используют один конфигуратор.
- Русская и английская локали совпадают по числу ключей (133/133).

## Gate

Lint 0/113, PHPStan level max 0, PHPUnit 357/357 (1034 assertions), покрытие 96.81% строк (1941/2005, порог 95%),
`composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и `composer.lock`.
