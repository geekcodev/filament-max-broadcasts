---
tags: [ consents, segments, dialogs, validation, select-all, payload, gate, v1.1.2 ]
date: 2026-10-30
---

# Реализация: согласие по сегменту, только диалоги, обязательность полей, выбор всех

## Проблема

По плану `.agents/plans/2026-10-30-consent-per-segment.md`: запрос согласия переезжает из списка рассылок в сегмент; в
сегменты попадают только диалоги; рассылка требует сегменты или получатели; в селекты добавляется «Выбрать всех»; в
селекте сегментов рассылки только сегменты с получателями и бейдж их числа.

## Решение

- Payload кнопок: `consent:<segmentId>:<action>`. Legacy `consent:<action>` относится к стартовому сегменту. Разбор —
  регулярным выражением в `HandleConsentCallback`; неизвестный сегмент игнорируется.
- `ConsentService`: `optIn/optOut(chatId, userId, segmentId)`, `answeredChatIds(segmentId)`, `findSegment(?id)`
  (null = стартовый). `ConsentRequestService`: `eligibleChats/sendRequest(BroadcastSegment)`.
  `SendConsentRequestsJob($segmentId)` с локом `consent:send-request:{segmentId}`.
- Кандидаты запроса — активные диалоги реестра без записи для этого сегмента. Получатели самого сегмента не нужны:
  иначе запрос не дойдёт до тех, кто ещё не в сегменте. Это уточнение к плану, оно зафиксировано в release notes.
- Кнопка `Resources\Actions\RequestConsentAction` на странице сегмента и в строке таблицы; на списке рассылок удалена.
- `BroadcastSender::send()` получил необязательный параметр `$buttonRows`: кнопки сегмента передаются явно.
- Текст запроса: `consent.request_message` с `:segment`; кнопки «Да» / «Нет».
- Сегменты: `ChatSelectionField(dialogsOnly: true)` — варианты, поиск, подписи, «Выбрать всех» и правило
  `Support\DialogsOnlyRule` на сервере. `BroadcastService::resolveChats()` берёт сегменты только в диалоги.
- Рассылка: `requiredWithout` на `segment_ids` и `recipient_chat_ids`; `BroadcastService::create()` бросает
  `InvalidArgumentException` при пустом выборе; повтор без получателей показывает уведомление.
- Варианты селекта сегментов: `Support\BroadcastSegmentOptions` + `Services\BroadcastSegmentRecipients::dialogCounts()`
  (один запрос на все сегменты), сегменты с нулём не выводятся, бейдж с числом.
- Предупреждение о не-диалогах: в инфолисте сегмента и описанием колонки в таблице сегментов.
- Селекты: «Выбрать всех» через `hintAction` (Filament v5), `optionLabels()` без лимита 50 батчами по 500.

## Тесты

Сначала написаны падающие тесты (шаг 1, 17 падений по ожидаемым причинам). Затем реализация. Старые тесты, которые
описывали пустой выбор «всем чатам», переписаны под новое правило (`BroadcastServiceTest`, `BroadcastResourceTest`,
`BroadcastSegmentResourceTest`). Новые тесты: `SegmentConsentRequestTest`, `ConsentRequestServiceTest`,
`SendConsentRequestsJobTest`, `BroadcastSegmentOptionsTest`, добавления в `ChatSelectionFieldTest`,
`ConsentServiceTest`,
`HandleConsentCallbackTest`, `ConsentPollTypeTest`, `BroadcastServiceTest`, `BroadcastResourceTest`,
`BroadcastSegmentResourceTest`.

Итог: 291 тест, 783 assertions, OK.

## Нюансы

- Стартовый сегмент создаётся миграцией, поэтому в тестах счётчик сегментов начинается с 1. Тесты сравнивают с начальным
  значением.
- Правило `->rule()` с замыканием не работает при рендеринге формы: `$attribute` не резолвится. Перешли на объект
  `ValidationRule`.
- Чаты в тестах без `chat_type` не считаются диалогами: фикстуры сегментов и рассылок теперь создают диалоги явно.
- PHPStan: `getKey()` возвращает `mixed`, в тестах используется свойство `->id`.
- Не проверено и оставлено открытым (см. план, раздел 6): лимит payload в спецификации MAX; «Выбрать всех» и поиск в
  селекте получателей рассылки (покрыто только для сегмента); замер производительности на тысячах чатов; поведение
  выбранного сегмента, который стал пустым; текст подтверждения ответа не менялся.
- Таблица сегментов: предупреждение о не-диалогах выводится описанием колонки, отдельного теста нет.

## Gate

- `composer format` — 0 файлов с правками (после правок — повторно).
- `composer lint` — 0 файлов с правками.
- `composer analyse` (PHPStan level max) — 0 ошибок.
- `composer test` — 291 тест, 783 assertions, OK.
- `composer coverage` — 97.40% строк (1685/1730), 90.32% методов, порог 95%.
- `composer security-audit` — уязвимостей нет.
- Чистая копия `.ci-sim` без `vendor/` и `composer.lock` со свежим `composer install`: lint, PHPStan, PHPUnit, покрытие
  и audit зелёные. Каталог после проверки удалён.
