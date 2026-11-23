---
tags: [ config, env, naming, bc, permissions, bot, gate ]
date: 2026-11-20
---

# Приведение имён к схеме бриджа

## Проблема

Имена переменных пакета расходились с окружением: права в `laravel-max-client` задаются как
`MAX_PERMISSION_<RESOURCE>_<ABILITY>` и строкой `max_<ресурс>.<ability>`, а в рассылках были
`FILAMENT_MAX_BROADCASTS_PERMISSION_*` и `broadcasts.*`. Имя бота в адаптере отсутствует, но хост-приложения используют
общий ключ `MAX_BOT_USERNAME` в своём `config/webapp.php` и вручную переопределяют `bot_username` рассылок на него.

## Решение

- Права (env): `FILAMENT_MAX_BROADCASTS_PERMISSION_VIEW/CREATE/MANAGE` → `MAX_PERMISSION_BROADCASTS_VIEW/CREATE/MANAGE`.
- Права (строки): `broadcasts.view/create/manage` → `max_broadcasts.view/create/manage` — как `max_user.*`/`max_chat.*`
  в бридже. Обновлены дефолты конфига, fallback-строки в ресурсах, страницах, таблицах и действии, Gate-фикстуры тестов.
- Бот: `FILAMENT_MAX_BROADCASTS_BOT_USERNAME` → `MAX_BOT_USERNAME` (общий ключ приложения для диплинков).
- Остальные: `FILAMENT_MAX_BROADCASTS_USER_MODEL` → `MAX_BROADCASTS_USER_MODEL`,
  `FILAMENT_MAX_BROADCASTS_IMAGE_*` → `MAX_BROADCASTS_IMAGE_*`.
- `.env.example` переписан под новые имена и получил предупреждение: это эталон имён, переносить нужно только изменяемые
  строки, потому что `KEY=` — пустая строка, а не «взять дефолт».
- README дополнен схемой имён; AGENTS.md (правило 10 и структура) обновлён; в release notes v2.0.0 добавлены BC-пункты с
  таблицей переименований и напоминанием, что у хостов `env()` вызывается в их опубликованном конфиге.
- Добавлен `EnvVariablesTest`: сверяет набор `env('...')` из конфига с `.env.example` и проверяет дефолтные строки прав.

## Тесты

359 тестов / 1038 утверждений. Новые проверки: соответствие `.env.example` конфигу и дефолтные строки прав
`max_broadcasts.*`.

## Нюансы

- У существующих установок опубликованный `config/filament-max-broadcasts.php` продолжает читать старые env-имена, пока
  его не обновят или не перепубликуют: тихого слома нет, но новые имена заработают только после правки конфига.
- Строки прав — ломающее изменение: роли и разрешения spatie/Gate нужно обновить на `max_broadcasts.*`, старые строки
  плагин больше не проверяет.
- В бридже переменной имени бота нет — `MAX_BOT_USERNAME` остаётся ключом уровня приложения.
- Исторические release notes и журналы сохраняют старые имена: это факт прошлых версий.

## Gate

Lint 0/114, PHPStan level max 0, PHPUnit 359/359 (1038 assertions), покрытие 96.81% строк (1941/2005, порог 95%),
`composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и `composer.lock`.
