---
tags: [ consent, callback, api ]
date: 2026-09-08
---

# Ответ на callback согласия — убрать кнопки + подтверждающее сообщение

## Контекст

Пользователь заметил: после нажатия на callback-кнопку «Согласен»/«Не согласен» в MAX ничего не происходило. Запрос:
сделать так, чтобы после нажатия кнопки убирались, а в ответ приходило сообщение с подтверждением — согласился
пользователь на рассылку или отказался.

## Что было

`HandleConsentCallback` после обработки действия вызывал только `ApiClient::sendAnswer(callbackId,
notification: 'Спасибо! Ваш ответ учтён.')` — это «тост» (notification), кнопки оставались на месте, и отдельного
сообщения в чат не отправлялось.

## Что сделано

1. `src/Listeners/HandleConsentCallback.php`:
    - `removeButtons()` — `ApiClient::editMessage(messageId, NewMessageBody)` убирает inline-клавиатуру из исходного
      сообщения. **Текст опроса передаётся обратно** (`text` из `$callback->message?->body?->text`,
      `format` из body или `TextFormat::Html`) — MAX-овский `PUT /messages` это полная замена тела, редактировать пустым
      телом нельзя (затирает текст). Если текст в апдейте недоступен — edit пропускается с warning (graceful
      degradation), подтверждение всё равно уходит.
    - `sendConfirmation()` — `ApiClient::sendMessage(new Recipient(chatId), NewMessageBody::create(text:
     $confirmation, format: TextFormat::Html))` отправляет в чат подтверждение.
    - `acknowledgeCallback()` — `sendAnswer(callbackId, notification: $confirmation)` подтверждает callback MAX-у
      (sendAnswer обязателен: без `$message`/`$notification` кидает `InvalidArgumentException`).
    - messageId берётся из `$update->messageId` (фолбэк `$callback->message?->body?->mid`). Каждая операция обёрнута в
      try/catch `MaxApiException` (покрывает и `RateLimitException`, и `InvalidResponseException` — оба наследники
      `MaxApiException`) с логированием без чувствительных данных.
2. `config/filament-max-broadcasts.php`: `consent.answer_notification` заменён на два сообщения —
   `answer_notification_opt_in` («Вы согласились на получение рассылок.») и `answer_notification_opt_out» («Вы
   отказались от получения рассылок.»).
3. `tests/Unit/Listeners/HandleConsentCallbackTest.php`: callback-update получил `message_id` + контекст
   `callback.message.body` (text «Согласны ли вы получать наши новости и акции?», format html). Тесты opt-in/opt-out
   проверяют 3 API-вызова — `PUT /messages` (editMessage: текст сохранён, attachments нет), `POST /messages`
   (sendMessage с текстом подтверждения), `POST /answers` (sendAnswer с callback_id). Добавлен тест graceful degradation
   (нет текста опроса → edit пропускается, 2 вызова, подтверждение уходит).

## Решения/отклонения

- Тексты подтверждений держим в конфиге (по образцу прежнего `answer_notification`), а не в lang-файлах — так уже
  устроен блок `consent`. `.env.example` не затронут (новых `FILAMENT_MAX_BROADCASTS_*` переменных нет).
- `editMessage` без text перезаписывает сообщение; для MAX текст/вложения остаются, кнопки убираются. Если
  `messageId` недоступен в апдейте — кнопки остаются, но подтверждение всё равно уходит (graceful degradation).
- Подтверждение дублируется и как notification в `sendAnswer` («тост» у нажавшей кнопку), и как отдельное сообщение в
  чат — так ответ виден всегда.
- Direct `ApiClient` в listener — консистентно с прежним дизайном (listener уже держал ApiClient для sendAnswer); запрет
  AGENTS про «прямые вызовы из Filament/Page» не нарушен; подтверждение — интерактивный ответ, а не рассылка
  (BroadcastSender для рассылок со снимком получателей/статистикой).

## Gate

- lint (php-cs-fixer): 0 ошибок.
- analyse (PHPStan level max): 0 ошибок (в т.ч. замечание `nullsafe.neverNull` исправлено).
- test (PHPUnit): 154 теста / 442 assertions, зелёные.
- audit: 0 критичных («No security vulnerability advisories found»; в первом прогоне был сетевой timeout — со второго,
  когда сеть вернулась, audit зелёный).