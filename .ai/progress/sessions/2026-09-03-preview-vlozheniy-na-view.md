# Сессия 2026-09-03 — превью вложений и ссылки на файлы на детальной странице рассылки

## Контекст

Пользователь заметил, что на детальной странице созданной рассылки (`ViewBroadcast`) отображаются инпуты загрузки файлов
(FileUpload), которые не несут функциональности просмотра, их можно скрыть. Также не работают превью картинок. Запрос:
сделать превью картинок, а для остальных файлов — ссылки, чтобы можно было открыть и посмотреть, если превью невозможно.

## Что сделано

### `src/Resources/Schemas/BroadcastForm.php`

- Три `FileUpload` (images/videos/files) — добавлен `->hiddenOn('view')`: на странице просмотра инпуты загрузки скрыты
  (остаются только на форме создания).
- Раздел «Вложения» (`attachmentsList()`) переписан на `RepeatableEntry::make('attachments')` с двумя
  взаимно-исключающими подкомпонентами на строку:
    - `ImageEntry::make('path')` — превью изображения. `->disk($imageDisk)`, `->height(120)`, `->square()`,
      `->extraImgAttributes(['loading' => 'lazy'])`, state — storage-path вложения. Видим при
      `isImagePreviewable()` (тип Image + непустой путь).
    - `TextEntry::make('path')` — ссылка «тип — имя файла» для прочих типов (видео/аудио/файл) и как фолбэк для
      изображений с недоступным путём. `->html()`, state строит `<a href="URL">...` через
      `Storage::disk('public')->url($path)`, `target="_blank" rel="noopener nofollow"`, экранирование `e()`.
- Хелперы: `attachmentPath()` (trim пути или null), `attachmentFileUrl()` (URL файла или null),
  `isImagePreviewable()` (Image + непустой путь).
- Новые импорты: `Filament\Infolists\Components\ImageEntry`, `GeekCo\MaxPhpClient\Enum\UploadType`,
  `Illuminate\Support\Facades\Storage`.

### Решение по URL (важно)

Превью делаем нативно: `ImageEntry->disk()` + storage-path, а НЕ готовый URL в `->state()`. Причина —
`ImageEntry::getImageUrl()` (vendor, строка 216) отдаёт стате без изменений только если это валидный URL или
`data:`; относительный путь от `Storage::url()` прошёл бы ветку диска и превратился в двойной `/storage/storage/...`.
`TextEntry`-ссылку строим через `attachmentFileUrl()`.

### `tests/Feature/Resources/BroadcastResourceTest.php`

- Новый тест `testViewPageShowsImagePreviewAndLinksForOtherFiles`: `Storage::fake('public')`, кладёт
  `images/photo.png` и `files/doc.txt`, создаёт Broadcast c двумя вложениями (Image + File), GET на view и проверяет в
  HTML: присутствие `<img `, `src="<imageUrl>"`, `href="<fileUrl>"`. Импорты `UploadType`, `Storage`; `assertIsString`
  перед `assertStringContainsString` (PHPStan: `getContent()` — `string|false`).

## Gate

- `composer format` / `composer lint` — 0.
- `composer analyse` (level max, Larastan; предв. `rm -rf .phpstan-cache`) — No errors.
- `composer test` — 91 tests, 254 assertions, OK.
- `composer audit` — 0 критичных (сервер-кэш предупреждение — некритично).

## Дальше

- Коммит/релиз — только по явному запросу пользователя (шаг 22 AGENTS).