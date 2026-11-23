<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Диск вложений рассылки и ссылки на них для админки.
 *
 * Вложения уходят в MAX через uploadMedia и дальше отдаются серверами MAX, поэтому
 * публичный HTTP-доступ к диску не нужен: по умолчанию файлы лежат на приватном
 * диске `local`, а в админке показываются по временным подписанным ссылкам.
 * Диск настраивается: локальный публичный, локальный приватный или объектное
 * хранилище (S3 и подобные) — для последних ссылки всегда подписанные.
 */
final class AttachmentStorage
{
    public const DEFAULT_DISK = 'local';

    public const URL_TTL_MINUTES = 30;

    public static function disk(): string
    {
        $disk = trim(config()->string('filament-max-broadcasts.image.disk', self::DEFAULT_DISK));

        return $disk !== '' ? $disk : self::DEFAULT_DISK;
    }

    public static function isPublicDisk(): bool
    {
        $disk = self::disk();
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            return false;
        }

        $driver = $config['driver'] ?? null;

        // Прямой URL осмыслен только у локального публичного диска: у объектного
        // хранилища даже публичный бакет безопаснее отдавать подписанной ссылкой.
        if ($driver !== 'local' && $driver !== 'public' && $disk !== 'public') {
            return false;
        }

        return $disk === 'public'
            || $driver === 'public'
            || ($config['visibility'] ?? null) === 'public';
    }

    /**
     * Ссылка на вложение для админки: публичный URL для публичного диска,
     * временная подписанная ссылка для приватного. Null — файла нет или диск
     * не умеет временные ссылки (тогда в UI остаётся только имя файла).
     */
    public static function url(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        $storage = Storage::disk(self::disk());

        if (! $storage->exists($path)) {
            return null;
        }

        if (self::isPublicDisk()) {
            $url = trim($storage->url($path));

            return $url !== '' ? $url : null;
        }

        try {
            return $storage->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
        } catch (\Throwable) {
            return null;
        }
    }
}
