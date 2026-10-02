# v1.0.4

Ресурс «Согласия» и поддержка пустых сегментов получателей.

## Что изменилось

**Ресурс «Согласия»: просмотр и удаление.** Добавлен отдельный read-only Filament-ресурс
`BroadcastConsentResource` (slug `broadcast-consents`, иконка shield-check, навигация после «Сегментов»), который
показывает список всех фактов согласия на рассылку — кто, когда и как ответил. В таблице: ID, MAX `chat_id` (с поиском),
название сегмента, действие (бейдж «Согласен»/«Не согласен»), источник и дата, плюс фильтр по действию. Создание и
редактирование записей отключены — их создаёт только механизм согласий; в списке доступно удаление. Права переиспользуют
существующие `broadcasts.view` (просмотр) и `broadcasts.manage` (удаление), модель — переопределяемая через
`consent.consent_model`. Ресурс регистрируется в плагине и переопределяется через
`->consentResource()` по образцу остальных ресурсов пакета. У enum `BroadcastConsentAction` появился метод `labels()`
(нужен фильтру таблицы).

**Пустые сегменты разрешены.** Поле выбора получателей в сегменте (`chat_ids`) больше не помечено as обязательное.
Раньше форма запрещала сохранить сегмент без получателей стандартным правилом «required» (в хостах без русского
`validation.php` выводился сырой ключ), хотя пустой сегмент — легальное состояние: миграция создаёт сегмент «Новости и
акции» с пустым списком, а `ConsentService` пересчитывает его в пустой после opt-out всех участников. Теперь пустой
сегмент создаётся и редактируется без ошибки; при использовании в рассылке он не добавляет получателей.

## Изменения схемы и конфигурации

Схема БД и `config/filament-max-broadcasts.php` не менялись — новых `FILAMENT_MAX_BROADCASTS_*` переменных нет. В
`lang/{ru,en}` добавлены блоки `consent.resource` и `consent_table` для подписей ресурса и его таблицы.

## Тесты

158 тестов (454 assertions). Добавлен `BroadcastConsentResourceTest` (доступ без прав → 403, доступ с правами, листинг
согласий с бейджами действий). В `BroadcastSegmentResourceTest` добавлен тест создания сегмента с пустым
`chat_ids`. PHPStan level max — без ошибок, PSR-12, `declare(strict_types=1)`. Gate целиком:

1. `php-cs-fixer --dry-run` — 0.
2. PHPStan level max (Larastan) — 0 ошибок.
3. PHPUnit — 158 тестов / 454 assertions, зелёные (failOnRisky/failOnWarning).
4. `composer audit` — 0 критичных.

---

[Laravel](https://laravel.com) · [Filament](https://filamentphp.com) · [laravel-max-client](https://github.com/geekcodev/laravel-max-client) · [max-php-client](https://github.com/geekcodev/max-php-client)