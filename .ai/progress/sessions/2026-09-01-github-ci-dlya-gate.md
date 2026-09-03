# Сессия: GitHub CI для Gate (по образцу filament-max-chat)

Дата: 2026-09-01. Папка: `/home/user/web/filament-max-broadcasts`.

## Задача
«Создай GitHub CI для запуска Gate как в filament-max-chat».

## Что сделано
- Изучён эталон: `/home/user/web/filament-max-chat/.github/workflows/ci.yml` (4 джобы: lint, analyse, test, audit).
- Создан `/home/user/web/filament-max-broadcasts/.github/workflows/ci.yml` в том же стиле:
  - триггеры: `push`/`pull_request` на `main`;
  - каждая джоба: `actions/checkout@v4` → `shivammathur/setup-php@v2` (PHP 8.4, `tools: composer:v2`,
    `coverage: none`) → `actions/cache@v4` для `vendor` (ключ по `hashFiles('composer.lock')`) →
    `composer install --no-interaction --no-progress --prefer-dist` → соответствующий скрипт Gate;
  - `permissions: contents: read` (минимальные права).

## Отличия от эталона (целевые, небольшие)
1. В джобе `test` добавлены `extensions: dom, fileinfo, libxml` — `ext-dom`/`ext-libxml`/`ext-fileinfo` заявлены
   в `require` composer.json; в эталоне их было два (dom, fileinfo).
2. `composer.lock` в репозиторий не коммитится (как и в `filament-max-chat` — проверено: у эталона lock тоже в
   `.gitignore` и не в git). Поэтому в CI `hashFiles('composer.lock')` даёт пустой ключ — кэш работает через
   `restore-keys: composer-`, зависимости резолвятся свежими. Поведение паритетно эталону.

## Почему стабилен именно analyse
Недавняя доработка phpstan.neon (`tmpDir: %currentWorkingDirectory%/.phpstan-cache`) исключает краш
`Undefined constant LARAVEL_VERSION` на reuse-кэше: в CI каталог `.phpstan-cache` создаётся заново на каждый бег.

## Gate после добавления workflow
- `composer lint`: 0 замечаний (41 файл).
- `composer analyse`: 0 ошибок (level max).
- `composer test`: 68 тестов, 184 assertions — зелёные.
- `composer audit`: 0 критичных (предупреждение контейнера о кэше composer — косметика).

## Статус
Шаги 0–21 плана завершены. CI — готовый артефакт для шага 22 (коммит ветки + публикация релиза v1.0.0).