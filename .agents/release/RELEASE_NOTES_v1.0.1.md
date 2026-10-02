# v1.0.1

Я выпустил новый релиз geekcodev/laravel-max-client до версии v1.1.1 так же есть другие обновления пакетов в composer, обнови все пакеты composer


Релиз-правки интерфейса детальной страницы рассылки: вложения теперь удобно просматривать.

## Что изменилось

- **Превью картинок** — на странице просмотра рассылки изображения-вложения отображаются миниатюрами (`ImageEntry`, диск
  из конфига `image.disk`, высота 120, lazy-load).
- **Ссылки на остальные файлы** — видео, аудио и обычные файлы отображаются кликабельной ссылкой «тип — имя файла»,
  которая открывает файл в новой вкладке. Если изображению недоступен путь/файл — вместо пустой миниатюры показывается
  такая же ссылка.
- **Скрыты нерабочие инпуты загрузки** — на странице просмотра поля `FileUpload` (изображения/видео/файлы)
  больше не показываются как заведомо нерабочие поля ввода; они остаются только на форме создания.
- **Локализация подписей типов вложений** — новые ключи `form.attachment_types.{image,video,audio,file}`
  в `lang/{ru,en}/broadcasts.php`, используются в тексте ссылок вложений.

## Тесты

91 тест (254 assertions, +1 feature-тест на рендер превью и ссылок). PHPStan level max (Larastan) — без ошибок, baseline
пустой; PSR-12, `declare(strict_types=1)`. CI (`github/workflows/ci.yml`): lint, PHPStan, PHPUnit, `composer audit`.

---

[Laravel](https://laravel.com) · [Filament](https://filamentphp.com) · [laravel-max-client](https://github.com/geekcodev/laravel-max-client) · [max-php-client](https://github.com/geekcodev/max-php-client)