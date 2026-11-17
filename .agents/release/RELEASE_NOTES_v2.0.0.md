# v2.0.0

Синхронизация с ядром `geekcodev/max-php-client` **v2.0.0** и адаптером `geekcodev/laravel-max-client` **v2.0.0**.
Версия ломающая: события MAX типизированы, кнопки и вложения собираются отдельными классами.

## Новое

- `HandleConsentCallback` принимает `MessageCallbackUpdate`: `chat_id` берётся из `message.recipient`, пользователь — из
  `callback.user` (или отправителя сообщения), `message_id` — из `message.body.mid`. Текст опроса для fallback-правки
  редактируется в `TextFormat::Html` (поля `format` в теле ответа больше нет).
- Кнопки: `Keyboard` + `CallbackButton`/`LinkButton`; `BroadcastTypeContract::buttonRows()` возвращает
  `list<list<Button>>`, клавиатура отправляется через `InlineKeyboardAttachmentRequestPayload`.
- Вложения: `PhotoAttachmentRequest` (token/url), `VideoAttachmentRequest`, `AudioAttachmentRequest`,
  `FileAttachmentRequest`; `UploadEndpoint` → token.
- Ответ на callback — `answerCallback()` вместо `sendAnswer()`.

## Изменение (BC)

- Constraints: `geekcodev/max-php-client` `^1.1.8` → `^2.0`, `geekcodev/laravel-max-client` `^1.2.0` → `^2.0`
  (предрелизная ветка `new` — `dev-new`).
- `BroadcastTypeContract::buttonRows()` и `ConsentPoll::buttonRowsFor()` возвращают `list<list<Button>>` вместо
  `list<InlineKeyboardButtonRow>`: кастомные типы рассылок требуют правки.
- Callback-payload без сообщения (нет `callback.message`/`message`) не обрабатывается: чат неизвестен.

## Затронутые сценарии

- **Запрос согласия**: кнопки «Да»/«Нет» несут `consent:<segmentId>:<action>`, ответ записывается, сообщение с опросом
  удаляется либо редактируется без кнопок.
- **Рассылка**: текст, медиа и кнопки-диплинки отправляются как раньше, изменилась только сборка вложений.
- **Согласия**: `opt_in`/`opt_out` обновляют сегмент и журнал без изменений в поведении.

## Качество

Lint 0/105, PHPStan level max 0, PHPUnit 302/302 (819 assertions), покрытие 96.96% строк (1720/1774), `composer audit`
0. Серверная проверка вебхуков в тестовом проекте — обязательное условие выпуска.
