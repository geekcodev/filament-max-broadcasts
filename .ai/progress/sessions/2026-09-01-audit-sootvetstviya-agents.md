# Сессия: аудит соответствия AGENTS.md / production-grade

Дата: 2026-09-01. Папка: `/home/user/web/filament-max-broadcasts`.

## Цель
Ответить на вопрос пользователя: «вся реализация плана соответствует AGENTS.md и применяет best practices и
production-grade?» — провести честный аудит, исправить найденное, не подменяя результаты.

## Что проверено
Полный ревью всех 22 файлов `src/`, миграций, конфига, `.env.example`, `lang/{ru,en}`, `composer.json`,
`phpstan.neon`, `phpunit.xml`, тестов и фикстур. Сверка с эталоном хоста `/home/user/web/chisto-service-mini-app`:
`app/Services/BroadcastService`, `MaxBot::sendBroadcast`, `BroadcastTextSanitizer`, `Jobs/SendBroadcastJob`.

## Результат сверки с эталоном
- `SendBroadcastJob`, `BroadcastService`, `BroadcastTextSanitizer`, `BroadcastSender` — честный перенос логики хоста
  (вплоть до лог-подписей и per-batch-обновления счётчиков), с целевыми улучшениями: параметры очереди
  (batch_size/tries/timeout/backoff) из конфига, guard `trim($imagePath)` в `BroadcastSender`, configurable
  `image.disk`, санитайзер идентичен.
- Разрешённые/осознанные замечания (унаследованы от хоста, отражены в журнале): фото грузится на каждого получателя
  (upload token не кэшируется — как в хосте); `Storage::disk()->path()` работает только с локальными дисками
  (дефолт `public`); `appDeepLink` без urlencode (параметры — только из конфига); `resolve()` грузит чаты в память
  (как в хосте).

## Найденные и исправленные отклонения
1. **A01 / fail-open страницы просмотра.** `BroadcastResource` не переопределял `canView()`. В не-strict Filament-хосте
   (стандартное поведение) `get_authorization_response()` при отсутствии policy даёт `Response::allow()` — просмотр
   `/admin/broadcasts/{id}` был доступен любому авторизованному пользователю без `broadcasts.view`. Добавлен
   `canView(Model $record)` на том же праве, что и `canAccess` (`permissions.view`). Попутно это закрывает видимость
   `ViewAction` в таблице.
2. **Захардкоженная подпись UI.** `BroadcastRecipientsRelationManager::$title = 'Получатели'` — нарушение правила
   «подписи UI — через lang-файлы». Заменено на `static getTitle(): string` с
   `__('filament-max-broadcasts::broadcasts.recipients.title')`.
3. **Нестабильный PHPStan.** Краш `Undefined constant Larastan\Larastan\LARAVEL_VERSION` при reused-кэше: стартовый
   краш из-за устаревшего `/tmp/phpstan` внутри контейнера (Larastan `bootstrap.php` пропускается при закэшированном
   result cache). Перенесён `tmpDir` в проектный `.phpstan-cache` (в `.gitignore`); analyse стабилен на повторных
   запусках.

## Тесты
Добавлен регрессионный `testViewPageIsForbiddenWithoutViewPermission` (проверяет 403 без права view).

## Gate после правок
- `composer lint`: 0 замечаний (41 файл).
- `composer analyse`: 0 ошибок (level max; src/config/database чисты, тестовые ошибки в baseline).
- `composer test`: 68 тестов, 184 assertions — зелёные (failOnRisky/failOnWarning).
- `composer audit`: 0 критичных (предупреждение контейнера о недоступном кэше композера — косметика).

## Статус
Шаги 0–21 плана — завершены. Шаг 22 (коммит/публикация) — только по явному запросу пользователя. Готово к
`v1.0.0` (README + черновик RELEASE уже на месте).