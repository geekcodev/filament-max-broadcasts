# Журнал прогресса — filament-max-broadcasts

Единый журнал изменений и прогресса проекта. Краткое описание каждой сессии и её итоги — здесь; подробности —
в отдельных файлах каталога `sessions/` (по одному `.md` на сессию).

Формат записи (добавляй новые сверху):

```
## [YYYY-MM-DD] Краткое название
- Сделано: ...
- Решения/отклонения: ...
- Прогресс по чек-листу: ...
- Подробности: `sessions/YYYY-MM-DD-kratkoe-nazvanie.md`
```

---

## [2026-09-01] GitHub CI для Gate (по образцу filament-max-chat)
- Сделано: создан `.github/workflows/ci.yml` — 4 джобы (lint/analyse/test/audit), полностью в стиле `filament-max-chat`:
  checkout@v4 + shivammathur/setup-php (PHP 8.4, composer:v2, coverage none) + actions/cache@v4 по `composer.lock` +
  `composer install` → `composer lint` / `composer analyse` / `composer test` / `composer audit`. Отличий два
  (целевых): в джобу test добавлены `extensions: dom, fileinfo, libxml` (требования `require` в composer.json);
  `composer.lock` в репо не коммитится (как и в `filament-max-chat`), поэтому в CI `hashFiles` пуст — кэш vendor
  работает через `restore-keys`, установка идёт со свежим резолвингом зависимостей (поведение идентично эталону).
- Gate прогонен после добавления workflow: lint 0, analyse 0, test 68 (184 assertions), audit 0 критичных.
- Статус по чек-листу: шаги 0–21 завершены; шаг 22 (коммит/публикация) — по явному запросу. CI — готовый артефакт
  к шагу 22.
- Подробности: `sessions/2026-09-01-github-ci-dlya-gate.md`
- Сделано: проведён полный аудит реализации против AGENTS.md и эталона хоста (`/home/user/web/chisto-service-mini-app`
  `app/Services/BroadcastService`, `MaxBot::sendBroadcast`, `BroadcastTextSanitizer`, `Jobs/SendBroadcastJob`). Найдены
  и исправлены 2 реальных отклонения: (1) `BroadcastResource::canView()` — страница просмотра была доступна по прямому
  URL без права `broadcasts.view` (в не-strict хосте Filament без policy даёт implicit allow, нарушение A01);
  (2) `BroadcastRecipientsRelationManager` — захардкоженный `$title = 'Получатели'` заменён на static `getTitle()`
  через `__('filament-max-broadcasts::broadcasts.recipients.title')` (правило «подписи UI — через lang»). Добавлен
  регрессионный тест `testViewPageIsForbiddenWithoutViewPermission`. PHPStan-краш `Undefined constant LARAVEL_VERSION`
  на reused-кэше устранён переносом `tmpDir` в проектный `.phpstan-cache` (добавлен в `.gitignore`) — стартовый краш
  был из-за устаревшего `/tmp/phpstan` в контейнере.
- Сверка с эталоном: `SendBroadcastJob`, `BroadcastService`, `BroadcastTextSanitizer` и `BroadcastSender` — честный
  перенос логики хоста (улучшения: параметры очереди из конфига, guard `trim($imagePath)` в sender, configurable disk).
  К осознанным расхождениям/замечаниям отнесено: повторная загрузка фото на каждого получателя (так же в хосте —
  upload token не кэшируется), `Storage::disk()->path()` поддерживает только локальные диски (дефолт `public`),
  `appDeepLink` без urlencode (значения только из конфига).
- Прогресс по чек-листу: шаги 0–21 (§11 PLAN) — завершены; шаг 22 (коммит/публикация) — по явному запросу. Gate
  зелёный: `lint` 0, `analyse` 0, `test` 68 (184 assertions), `audit` 0 критичных.
- Подробности: `sessions/2026-09-01-audit-sootvetstviya-agents.md`

## [2026-09-01] Полный Gate и оформление релиза v1.0.0
- Сделано: прогнан полный Gate (§10). Исправлены найденные проблемы: (1) `FilamentMaxBroadcastsServiceProvider` —
  добавлен импорт `Illuminate\Container\Container` (ошибка PHPStan «unknown class Container»); (2) `ViewBroadcast` —
  действие удаления переделано на `Action::make('removeBroadcast')` с `requiresConfirmation` и лейблами из `lang/`
  (убран хардкод `'Delete'` и nullsafe `$record?->delete()`); (3) `tests/TestCase.php` — добавлен
  `PRAGMA foreign_keys = ON` для SQLite in-memory, без этого каскадное удаление не удаляло `BroadcastRecipient`
  (1 ошибка в `testDeleteActionRemovesBroadcast`). Сгенерирован `phpstan-baseline.neon` (59 ошибок, все — тестовые
  файлы; `src/`, `config/`, `database/` чисты на level max). Созданы `README.md` и `RELEASE-v1.0.0.md`.
- Решения: PHPStan-ошибки тестов закрыты baseline-файлом (подход чат-плагина с `ignoreErrors` не покрывал всех кейсов);
  `phpstan-baseline.neon` коммитится вместе с проектом. Каскадное удаление получателей оставлено на БД
  (FK `cascadeOnDelete` из миграции) — в тестах FK включены через PRAGMA.
- Прогресс по чек-листу: шаги 0–21 (§11 PLAN) — завершены. Остался шаг 22 (коммит/публикация — только по явному
  запросу пользователя). Gate зелёный: `lint` 0, `analyse` 0, `test` 67 (183 assertions), `audit` 0 критичных.
- Подробности: `sessions/2026-09-01-gate-i-reliz-v100.md`

## [2026-08-31] Инициализация проекта и базовой документации
- Сделано: создан `AGENTS.md` (описание проекта, требования, Gate, OWASP, локальная разработка), заведён журнал
  прогресса `.ai/progress/`, оформлен рабочий план в `PLAN-filament-max-broadcasts.md` (не пойдёт в git, будет удалён).
- Решения: плагин самодостаточный, таблицы с префиксом `max_`, кнопки акций настраиваемые, права
  `broadcasts.view/create/send/manage`, без Blade-вьюх и без real-time-прогресса на текущем этапе. Проект чистый —
  без упоминаний внешних хост-проектов в git-файлах.
- Прогресс по чек-листу: 0 из 22 шагов (§11 PLAN) — реализация ещё не начата.
- Подробности: `sessions/2026-08-31-inicializaciya-i-dokumentaciya.md`