---
tags: [ workflow, agents, coverage, ci, docs, gate ]
date: 2026-10-02
---

# Организация работы по образцу соседних пакетов: Gate покрытия, единый CI, рабочая память в .agents

## Проблема

`AGENTS.md` отставал от практики соседних пакетов (`filament-max-chat`, `filament-max-users`,
`laravel-max-client`): не было ветки `dev` и релизного процесса, не различались «текст коммита» и «коммит»,
отсутствовали правила про `git add .`, conventions-таблица, раздел про форму реестра `laravel-max-client` и
гейт покрытия. Рабочая память лежала в `.ai/progress/` и локальных `RELEASE-*.md` в корне — то есть не
переживала `git clone` на другой машине. CI состоял из четырёх job, покрытие не проверялось, дистрибутив пакета
тащил за собой тесты и workflow. Отдельно: git-индекс содержал staged-записи миграций `000005_AD_add_...` и
`000006_rename_...`, которых в рабочем дереве нет.

## Решение

- Рабочая память перенесена в `.agents/`: `journals/JOURNAL.md` + `journals/sessions/` (21 запись, четыре
  файла сессий созданы при переносе), `plans/`, `release/RELEASE_NOTES_v*.md`, `skills/`. Каталог отслеживается
  git, но исключён из архива пакета через `export-ignore` в `.gitattributes` (паттерн `/.agents/**` со слешем —
  иначе `git check-attr export-ignore` молча отдаёт `unspecified`).
- `JOURNAL.md` приведён к формату соседних пакетов: таблица-указатель (дата, файл, теги, описание) и тела
  сессий в отдельных файлах. Решение 2026-09-02 держать release-notes локальными отменено — они в git.
- `AGENTS.md` переработан: ветки `dev`/`main`, релизный процесс (release-notes → merge → тег → GitHub Release →
  Packagist), различение «текст коммита»/«коммит», запрет `git add .`, динамическая сверка версий
  (`composer show`), описание формы реестра 1.1.x против 1.2, conventions-таблица, раздел 4.1 про `.agents`,
  обязательный Gate из шести шагов, OWASP Top 10 с поправкой про публичный диск вложений, 21 gotcha и
  финальный чек-лист. Ограничение: constraint `geekcodev/laravel-max-client` не повышаем до 1.2 — это решение
  пользователя, задача зафиксирована пунктом 1 нового плана.
- Gate покрытия: `scripts/check-coverage.php` (из `filament-max-chat`), скрипт `composer coverage`
  (`phpunit --coverage-clover build/coverage.xml && php scripts/check-coverage.php 90`). Порог 90% — измеренный
  факт 90.76% строк, а не 95% как у соседей; понижать ниже нельзя, это храповик.
- CI: `.github/workflows/ci.yml` — один job `quality` (push в `main`/`dev`, PR в `main`): lint → phpstan →
  phpunit с покрытием и проверкой порога → `composer audit`. Кэш Composer по `composer.json` (без lock).
- `phpstan.neon` получил `tmpDir: .phpstan-cache` и `reportUnmatchedIgnoredErrors: false`: в CI зависимости
  новее локальных, часть хелперных записей baseline там просто не возникает. `.php-cs-fixer.dist.php`
  добавлен `scripts/` в finder.
- Правило про текст коммита вынесено из §2 в правила для агентов (§3, пункт 3) и дополнено таблицей
  «запрос → ответ»: «краткий текст коммита» (и «текст коммита» без уточнений) — одна строка head на английском по
  Conventional Commits по всем изменениям ветки, без body и без создания коммита; body — только по явной просьбе;
  «закоммить» — коммит; «запушь» — только push. Нумерация §3 пересобрана, ссылка на правило про скиллы поправлена на 14.
- Git-индекс сброшен (`git reset`), staged-записи устаревших имён миграций исчезли; на диске шесть корректных
  миграций из HEAD.
- `scripts/check-coverage.php` получил порог по умолчанию 90% (как в `composer coverage`) вместо унаследованных
  от соседей 95%: без аргумента скрипт врал и падал на зелёном коде. Позже в тот же день порог поднят до 95%
  вместе с покрытием (см. сессию `2026-10-02-coverage-95.md`). Каталог `scripts` добавлен в paths PHPStan
  (level max), разбор XML — с `LIBXML_NONET`. В `.gitattributes` добавлены dev-файлы контейнера (`Dockerfile`,
  `docker-compose.yml`, `docker/**`, `.dockerignore`), которые иначе попадали в архив пакета.
- Создан активный план `.agents/plans/PLAN-filament-max-broadcasts.md`: переход на 1.2, покрытие до 95%,
  приватный диск вложений, синхронизация README.
- README: команда `composer coverage` с порогом, флаг `-T`, требование общего кэша для локов джоб, caveat про
  формы реестра `laravel-max-client`, раздел «История изменений» по версиям v1.0.0…v1.0.5.

## Тесты

Новых тестов нет — задача документационная, production-код не менялся. Проверено, что `.gitattributes`
экспортирует `.agents/`, `tests/`, `.github/` из архива, а `src/` — нет, и что workflow проходит строгую
проверку дубликатов ключей YAML (PyYAML `safe_load` дубликаты не ловит — gotcha 20).

## Нюансы

- Constraint `^1.1.0` шире, чем нужно коду, а `composer.lock` не коммитится: локально `laravel-max-client
  v1.1.1`, в CI разрешится свежая версия. Отсюда проверка Gate в условиях CI (gotcha 11) как обязательный
  шаг при изменении зависимостей.
- Форма реестра 1.1.x и 1.2.0 отличается (строка на пару пользователь-чат против строки на чат), поэтому
  `chat_id` объявлен без знака и отрицательные идентификаторы групп/каналов в MySQL 1.1.x не примет — код
  работает с int64.
- Диск вложений по умолчанию `public`: файлы рассылки доступны по HTTP, хотя `BroadcastSender` читает их по
  локальному пути и публичность не требуется. Дефолт не меняем молча — решение за пунктом 3 плана.
- Проверка «в условиях CI» сделана частично: `composer update --dry-run` показал дрейф основных зависимостей
  (`filament/filament` 5.7.8 → 5.9.0, `laravel/framework` 13.30.1 → 13.34.0), а полный Gate на чистой установке без
  lock не гонялся — сеть к Packagist в этой среде нестабильна. Дрейф зафиксирован пунктом 1 плана.

## Gate

- `docker compose exec -T app composer lint` — 0 файлов с правками.
- `docker compose exec -T app composer analyse` — `No errors`.
- `docker compose exec -T app composer test` — 159 тестов, 456 assertions, всё зелёное.
- `docker compose exec -T app composer coverage` — 90.76% строк (1286/1417), 78.61% методов, порог 90% пройден.
- `docker compose exec -T app composer security-audit` — 0 уязвимостей.
- Строгий разбор `.github/workflows/ci.yml` на дубликаты ключей и `git check-attr export-ignore` — чисто.
- `composer security-audit` сначала упирался в сеть (хост advisory API `packagist.org` недоступен, помогли
  `COMPOSER_IPRESOLVE=4` и повторы), затем показал реальную находку: `league/commonmark` 2.10.0 (транзитивно из
  `laravel/framework`) попадает под advisory от 2026-09-30 — GHSA-97jj-33gv-5xf9 (medium, обход DisallowedRawHtml) и
  GHSA-3q6v-r5mr-hxv8 (high, квадратичный DoS в GFM-таблицах), затронуты версии `<=2.10.1`. Обновлено до 2.10.3
  (`composer update league/commonmark`), после чего audit чист. `composer.lock` не коммитится, поэтому хосты получат
  фикс при следующем `composer update`.
- Коммит и push не выполнялись.