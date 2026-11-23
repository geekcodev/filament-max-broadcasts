---
tags: [ tests, coverage, gate, validators, jobs, services, ui, authorization ]
date: 2026-11-23
---

# Покрытие методов: с 87.22% до 97.75% и авторизация удаления сегмента

## Проблема

Покрытие строк было 96.09%, а методов — 87.22% (232/266): у многих методов оставались непройденными отдельные защитные
ветки (пустые входы, отсутствующие записи, неудачные fallback-вызовы), из-за чего метод не засчитывался покрытым. Целые
методы были непокрыты: правила валидации `DialogsOnlyRule` и
`SegmentConsentRule`, `ChatSelectionField::dialogIdsAmong`, `BroadcastSegmentResource::canDelete`,
`ServiceProvider::register` с кастомным резолвером.

Отдельно выяснилось, что `BroadcastSegmentResource::canDelete()` вообще не участвовал в авторизации:
`Resource::getDeleteAuthorizationResponse()` идёт через `get_authorization_response()` (политику модели), а политик в
пакете нет. Права удаления фактически жили только в явных `->authorize()` на действиях.

## Решение

Авторизация: `BroadcastSegmentResource` переопределяет `getDeleteAuthorizationResponse()` и делегирует
`canDelete()`, поэтому ресурсная проверка прав удаления теперь совпадает с `canDelete()` для всех путей, включая
`DeleteAction` без явного `->authorize()` и внешние вызовы. Поведение UI не изменилось: явные
`->authorize($managePermission)` на действиях оставлены как defense-in-depth.

Тесты добавлены на реальные сценарии, без искусственных обходов там, где ветка достижима:

- правила валидации прогоняются через `Validator::make()`: проверяется и сообщение, и валидные значения, и не-массив;
- `ChatSelectionField`: правило добавляется при `dialogsOnly: true`, `dialogIdsAmong()` возвращает только
  зарегистрированные диалоги и терпит пустой список;
- `BroadcastSegmentOptions::labelsFor()` с пустым/невалидным набором id, `AttachmentFile` с отключённым лимитом размера
  (`max_kb = 0`), `BroadcastRecipientsResolver::resolveForChatIds([])`;
- `ConsentService`: запросы по несуществующему сегменту и пустой список сегментов дают пустые результаты;
- `ChatRegistry::userId()/user()` для модели без связей реестра;
- биндинги провайдера: кастомный резолвер из `recipients.resolver` поднимается контейнером (фикстура
  `CustomResolver`), без настройки используется пакетный;
- `SendConsentRequestsJob::failed()` только логирует и не трогает сегмент;
- `SendBroadcastJob`: отмена между батчами, обновление счётчиков между батчами, возврат без завершения, когда после
  прохода остались pending-получатели;
- `HandleConsentCallback`: удаление опроса и fallback-правка падают оба, согласие всё равно записывается;
- feature-уровень: `canDelete()` и `getDeleteAuthorizationResponse()` по праву `manage`, пустая под-навигация для
  страницы без записи, предупреждение о не-диалоговых получателях в списке сегментов, `user_id` в журнале согласий,
  имена сегментов и заглушка на просмотре рассылки, отменённая рассылка с вложением в списке, повтор рассылки из таблицы
  без получателей.

Ветки, недостижимые через UI, покрыты прямыми вызовами через `ReflectionMethod` (в проекте это уже принято для
`getFormActions`): guards `ViewBroadcast::sendNow()/cancel()`, пустые `BroadcastService::resolveChats()` и
`consentedChatIds()`, fallback типа и `scheduled_at` в `CreateBroadcast::handleRecordCreation()`,
`BroadcastConsentsTable::segmentFilter()` с малформированным значением, `BroadcastTextSanitizer::unwrap()` без родителя.
Для `BroadcastSender::uploadToAttachment()` покрыты обе аварийные ветки: отсутствующий файл (reflection)
и загрузка без токена (мок транспорта без `token`).

## Тесты

- Новые файлы: `tests/Unit/Support/DialogsOnlyRuleTest.php`, `tests/Unit/Support/SegmentConsentRuleTest.php`,
  `tests/Unit/ServiceProviderBindingsTest.php`, фикстура `tests/Fixtures/CustomResolver.php`.
- Дополнены: `ChatSelectionFieldTest`, `BroadcastSegmentOptionsTest`, `AttachmentFileTest`,
  `ChatRegistryTest`, `BroadcastRecipientsResolverTest`, `ConsentServiceTest`, `BroadcastServiceTest`,
  `BroadcastSenderTest`, `SendConsentRequestsJobTest`, `SendBroadcastJobTest`, `HandleConsentCallbackTest`,
  `BroadcastTextSanitizerTest`, `BroadcastSegmentResourceTest`, `BroadcastConsentResourceTest`,
  `BroadcastResourceTest`.
- Итог: 404 теста / 1143 утверждения; покрытие 99.20% строк (2109/2126) и 97.75% методов (261/267).

## Нюансы

- Create-миграция `0000_03_000004` вставляет первичный сегмент «Новости и акции», поэтому в тестах нельзя опираться на
  абсолютное число сегментов: проверяется наличие созданной записи, а не `count() === 1`.
- `Dom\HTMLDocument` отбрасывает `Comment`-узлы до обхода дерева, поэтому ветку `sanitize()` с удалением комментария
  через публичный API не вызвать — тест на комментарий убран как вводящий в заблуждение.
- Для guard'а под-навигации достаточно мока `Filament\Resources\Pages\Page` (у базовой страницы нет
  `getRecord`); для PHPStan нужен `@var Page`.
- Оставшиеся 6 методов — аварийные ветки файловых операций `AttachmentFile`, не-скалярная защита
  `ConsentService::optedInChatIdsBySegment` и ветки `BroadcastTextSanitizer`; они перечислены в gotcha 18. Порог
  покрытия не понижался.

## Gate

Lint 0/120, PHPStan level max 0, PHPUnit 404/404 (1143 assertions), покрытие 99.20% строк (2109/2126, порог 95%) и
97.75% методов, `composer security-audit` — 0 advisories. Повторено в чистой копии без `vendor/` и
`composer.lock`: свежий `composer install` по `composer.json`, те же результаты.
