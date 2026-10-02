---
tags: [ consent, job, permissions, config ]
date: 2026-09-04
---

# Запрос согласия → кнопка на списке рассылок (вместо типа рассылки)

- Сделано (по уточнению пользователя — отправка согласия НЕ обычная рассылка): новая механика — **header-действие
  «Запросить согласие» на `ListBroadcasts`**. При нажатии в очередь ставится
  `SendConsentRequestsJob`, который рассылает **фиксированное сообщение** (config `consent.request_message`, «Согласны
  ли вы получать наши новости и акции?») с callback-кнопками «Согласен»/«Не согласен» всем активным чатам **без записи**
  в `max_broadcast_consents` (опрашиваются заново проигнорировавшие).
- Уточнённые правила пользователя: факт получения НЕ фиксируется; фиксируется только ответ (opt_in/opt_out); ответившие
  (в любую сторону) повторно запрос не получают. Источник «ещё не ответивших» —
  `ConsentService::answeredChatIds()` (все записи согласий) минус активные чаты.
- Сервис `Services/ConsentRequestService` (SRP): `eligibleChats()`/`eligibleChatIds()`/`sendRequest()` (считает
  кандидатов, диспатчит джоб, возвращает число). Job `Jobs/SendConsentRequestsJob`:
  `Cache::lock('consent:send-request')`, пере-резолв кандидатов на момент выполнения, батчи по `queue.batch_size`,
  отправка через `BroadcastSender` с типом
  `ConsentPoll` (кнопки), ошибки на получателя логируются без чувствительных данных.
- Уточнение пользователя: согласие привязано **именно к сегменту «Новости и акции»** — `consent.segment_name` теперь
  дефолтится в «Новости и акции» (было «Согласие на рассылку»); `answeredChatIds()` фильтрует записи по id этого
  сегмента, записи других сегментов в дедупе не участвуют. Остальные сегменты согласие не затрагивает.
- Изменения: из реестра `types` убран `consent` (оператор не создаёт запрос как обычную рассылку; enum `ConsentPoll`
  остался источником кнопок для джобы); в конфиг добавлен `consent.request_message`; lang ru/en — actions
  `request_consent*` и notifications `consent_request_started`/`consent_no_recipients`; кнопка под правом
  `permissions.create`, подтверждение через modal.
- Тесты: +17 (ConsentService answeredChatIds + скоуп по сегменту, ConsentRequestService, SendConsentRequestsJob —
  lock/исключение на получателя/пустые кандидаты; feature ListBroadcasts — видимость/скрытие действия, dispatch и skip).
  Итого 138 тестов / 384 assertions.
- Решения/отклонения: PHPStan level max — доступ к атрибутам `MaxChat` через `getAttribute()` + локальные `@var`
  (конвенция пакета, vendor-модель без `@property`); `Collection::values()->all()` не выводится как `list<int>` —
  используем `array_values(...)`; Filament-`assertActionVisible/Hidden` не известны PHPStan → `@phpstan-ignore
  method.notFound` (как `assertCanSeeTableRecords`).
- Gate: lint 0, analyse 0, test 138 (384 assertions), audit 0 критичных.
  `.ai/progress/PLAN-consent-callback.md`.
