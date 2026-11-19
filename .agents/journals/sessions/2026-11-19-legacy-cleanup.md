---
tags: [ legacy, registry, consent, cleanup, bc, gate ]
date: 2026-11-19
---

# Удаление legacy-слоя: реестр 1.1 и стартовый сегмент

## Проблема

Пакет требует `laravel-max-client` v2 (форма реестра 1.2), но в коде оставалась поддержка формы 1.1.x: ветки
`maxUser`/`user_id` в `ChatRegistry`, стабы `LegacyChat`/`PivotChat`/`PivotlessChat` и фикстура, собиравшаяся под обе
формы. Отдельно `ConsentService` сохранял совместимость со стартовым сегментом «Новости и акции»: необязательный
`segmentId`, `resolveConsentSegment()`, `consentChatIds()` и `segment_name` как рабочий дефолт. В новой модели согласие
всегда привязано к сегменту, а реестр существует только в форме 1.2.

## Решение

- `ChatRegistry` сведён к форме 1.2: `userRelation()` — `chatUsers.maxUser`, `user()`/`userId()` читают участников
  `max_chat_users` (с fallback на `maxUsers`, если связь не подгружена), `title` и `displayName` без legacy-веток.
  Удалён `chatInstance()` и проверки `method_exists` по форме реестра.
- `ConsentService`: `optIn/optOut/answeredChatIds/optedInChatIds` требуют `int $segmentId`; удалены
  `resolveConsentSegment()` и `consentChatIds()`, поиск сегмента по `null` и работа со стартовым сегментом по умолчанию.
  `consent.segment_name` остался только для миграции, создающей стартовый пустой сегмент.
- Фикстура `Chats` собирает только форму 1.2 (`max_chats` + `max_chat_users`); стабы `LegacyChat`, `PivotChat`,
  `PivotlessChat` удалены, `ChatRegistryTest` переписан под одну форму.
- README, `AGENTS.md`, release notes и конфиг синхронизированы; старые планы оставлены как история.

## Тесты

338 тестов / 938 утверждений. Удалены legacy-тесты реестра и тесты стартового сегмента; оставшиеся проверяют связи
`chatUsers.maxUser`, `maxUsers`-fallback, поиск по имени, `displayName`, `userId` и согласия строго по сегментам.

## Нюансы

- Это ломающее изменение в рамках v2.0.0: хосту с формой реестра 1.1 нужно сначала обновить `laravel-max-client` до v2 и
  выполнить его миграции.
- `segment_name` не удалён из конфига: его читает миграция, создающая стартовый сегмент. Сам сегмент остаётся обычным
  пустым сегментом и больше не участвует в согласии неявно.
- `RawChat`/`RawUser` остаются: они нужны для значений, которых нет в БД (`chat_id` строкой, `chat_type` строкой).

## Gate

Lint 0/111, PHPStan level max 0, PHPUnit 338/338 (938 assertions), покрытие 96.69% строк (1900/1965, порог 95%),
`composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и `composer.lock`.
