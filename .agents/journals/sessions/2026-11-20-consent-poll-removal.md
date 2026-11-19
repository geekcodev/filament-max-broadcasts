---
tags: [ consent, cleanup, architecture, gate ]
date: 2026-11-20
---

# Удаление псевдотипа ConsentPoll

## Проблема

`ConsentPoll` был не legacy-payload, а технической заглушкой: `BroadcastSender::send()` требовал
`BroadcastTypeContract`, поэтому опрос согласия был оформлен «типом рассылки», который нигде не регистрируется и не
показывается. Из-за контракта приходилось держать пустой `buttonRows()`.

## Решение

- `BroadcastSender::send()` принимает необязательный `?BroadcastTypeContract $type = null`; кнопки берутся из явного
  `$buttonRows`, иначе из типа, иначе пусто.
- Кнопки согласия вынесены в `Support\ConsentButtons` (`payload()`, `forSegment()`), тексты и префикс — из конфига.
- `ConsentPoll` удалён; `SendConsentRequestsJob` передаёт `null` вместо типа и явные кнопки.
- Удалён неиспользуемый ключ `type.consent` из `lang/ru` и `lang/en`.
- AGENTS, release notes и план остаточных замечаний обновлены.

## Тесты

335 тестов / 933 утверждения. `ConsentPollTypeTest` заменён на `ConsentButtonsTest`; ожидания в
`SendConsentRequestsJobTest` обновлены на `null` вместо `ConsentPoll::ConsentPoll`.

## Нюансы

- `ConsentButtonsTest` проверяет payload с сегментом, тексты кнопок из конфига и переопределение префикса.
- Публичный класс `ConsentPoll` удалён в рамках ломающего v2.0.0; хосты, которые на него ссылались, должны использовать
  `Support\ConsentButtons`.

## Gate

Lint 0/111, PHPStan level max 0, PHPUnit 335/335 (933 assertions), покрытие 96.69% строк (1896/1961, порог 95%),
`composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и `composer.lock`.
