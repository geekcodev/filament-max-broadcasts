---
tags: [ ui, form, rich-editor, broadcasts, cleanup, gate ]
date: 2026-11-23
---

# Высота RichEditor в форме рассылки: перенос из темы хоста в пакет

## Проблема

Высота поля текста рассылки задавалась CSS-правилом в теме хоста (`app/resources/css/filament.css`:
`.fi-fo-rich-editor-main .ProseMirror { min-height: 18rem }`). Это поведение конкретного поля пакета, а не темы
приложения: правило действовало на все RichEditor админки, не проверялось тестами и терялось при смене темы или переезде
хоста. По правилу «регрессии не чиним в стороннем проекте» место исправления — пакет.

## Решение

`BroadcastForm` объявляет высоту штатным API Filament:

```php
RichEditor::make('text')
    ->minHeight('18rem')
```

Filament v5 использует трейт `HasMinHeight`: значение рендерится в `--min-height` на обёртке, а
`rich-editor.css` применяет `min-height` к `.fi-fo-rich-editor-content` — тому же узлу, на который TipTap монтируется
через `x-ref="editor"` и которому добавляет класс `ProseMirror`. Поэтому кликабельная зона ниже текста сохраняется, а
CSS-костыль в теме хоста больше не нужен: правило из `app/resources/css/filament.css` удалено.

## Тесты

`BroadcastResourceTest::testMessageEditorUsesCustomMinHeight` находит RichEditor поля `text` через
`getFlatComponents()` на странице создания и проверяет `getMinHeight() === '18rem'`. Итог: 405 тестов / 1146
утверждений, покрытие 99.20% строк (2111/2128) и 97.75% методов (261/267).

## Нюансы

- Дефолт Filament — `10rem`; фикс закрепляет `18rem` только у поля рассылки, остальные RichEditor сохраняют дефолт.
- Публичный API, ключи конфига и переводов не менялись, миграции не тронуты — изменение совместимо с patch-релизом.
- Хосту нужен `composer update geekcodev/filament-max-broadcasts` после публикации ветки: до этого поле останется на
  дефолтных `10rem`, потому что в `vendor/` лежит предыдущая сборка пакета.

## Gate

Lint 0/120, PHPStan level max 0, PHPUnit 405/405 (1146 assertions), покрытие 99.20% строк (2111/2128, порог 95%) и
97.75% методов, `composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и `composer.lock`:
свежий `composer install` по `composer.json`, те же результаты.
