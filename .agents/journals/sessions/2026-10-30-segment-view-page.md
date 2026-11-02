---
tags: [ segments, filament, view-page, infolist, permissions, gate ]
date: 2026-10-30
---

# Страница просмотра сегмента рассылки

## Проблема

У ресурса «Сегменты» были только список, создание и редактирование: посмотреть состав сегмента, не открывая форму
правки, было нельзя. Форма не подходит на роль просмотра, потому что выбор получателей (`ChatSelectionField`) — это
редактируемое поле с серверным поиском, а не отображение.

## Решение

Добавлена страница `Resources\Pages\ViewBroadcastSegment` (`ViewRecord`, маршрут `/{record}`) с отдельным infolist
`Resources\Schemas\BroadcastSegmentInfolist`. Infolist показывает название, количество получателей, описание, дату
создания и список чатов: `chat_id` сегмента резолвятся через `ChatRegistry` (имена — `displayName()`), для отсутствующих
в реестре выводится подпись «Чат :id», для пустого сегмента — placeholder.

Права: `BroadcastSegmentResource::canView()` проверяет `broadcasts.view`, страница без права отдаёт 403. Правка и
удаление на странице просмотра и в таблице получили явный `->authorize($managePermission)`: в Filament v5 авторизация
header-действий страницы идёт через политику, а не через переопределённый `Resource::canEdit()`, поэтому без явного
`authorize()` кнопки видел бы и пользователь только с правом просмотра. В таблицу добавлено действие «Просмотр»
(`ViewAction::make()->iconButton()`).

Новые ключи `segment.view.*` добавлены и в `lang/ru`, и в `lang/en`.

## Тесты

`tests/Feature/Resources/BroadcastSegmentResourceTest.php`: 403 без права, доступ с одним `broadcasts.view` и скрытие
edit/delete, отображение названия/описания/имён получателей, фолбэк на `chat_id` и на placeholder пустого сегмента,
видимость edit/delete у администратора, удаление со страницы просмотра, действие «Просмотр» в таблице. Всего 19 тестов
класса.

## Нюансы

- `TextEntry::listWithLineBreaks()` принимает массив строк; пустой массив рендерится как placeholder, а не как пустой
  блок.
- `ChatRegistry::displayName()` на форме реестра 1.1.x без `title` даёт имя собеседника только для `ChatType::Dialog`,
  иначе возвращает `chat_id`; тест имён строит диалоги с `MaxUser`.
- Тесты не фиксируют локаль, поэтому строки переводов в assert'ах берутся через `__()`, а не литералами.
- Локальные зависимости обновлены `composer update`: `laravel-max-client` v1.2.3 (форма 1.2) и Filament v5.11.0. До
  обновления `composer audit` находил 4 advisory, после — чисто.

## Gate

- `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 245 тестов, 660 assertions, OK.
- `composer coverage` — 97.92% строк (1504/1536), 91.58% методов, порог 95%.
- `composer security-audit` — уязвимостей нет.
- Чистая копия `.ci-sim` без `vendor/` и `composer.lock` со свежим `composer install`: lint, PHPStan, PHPUnit, покрытие
  и audit зелёные. Каталог после проверки удалён.
