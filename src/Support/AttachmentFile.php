<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Локальный файл вложения для загрузки в MAX.
 *
 * Ядро `max-php-client` принимает только путь к локальному файлу, поэтому для
 * объектного хранилища (S3 и подобных) файл скачивается во временный файл и
 * удаляется после отправки. Локальный диск отдаёт путь напрямую, без копирования.
 */
final class AttachmentFile
{
    private const int TEMPORARY_PERMISSIONS = 0600;

    private function __construct(
        private readonly string $localPath,
        private readonly bool $temporary,
    ) {
    }

    public static function fromDisk(Filesystem $storage, string $storagePath): self
    {
        $storagePath = trim($storagePath);

        if ($storagePath === '') {
            throw new RuntimeException('Media file not found or not readable: empty path.');
        }

        if ($storage instanceof LocalFilesystemAdapter) {
            return new self(self::requireReadableFile($storage->path($storagePath)), false);
        }

        return new self(self::download($storage, $storagePath), true);
    }

    public function path(): string
    {
        return $this->localPath;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }

    public function cleanup(): void
    {
        if ($this->temporary) {
            self::remove($this->localPath);
        }
    }

    private static function requireReadableFile(string $localPath): string
    {
        if (! is_file($localPath) || ! is_readable($localPath)) {
            throw new RuntimeException('Media file not found or not readable: '.$localPath);
        }

        return $localPath;
    }

    private static function download(Filesystem $storage, string $path): string
    {
        if (! $storage->exists($path)) {
            throw new RuntimeException('Media file not found or not readable: '.$path);
        }

        self::assertWithinSizeLimit($storage, $path);

        try {
            $stream = $storage->readStream($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to read media file: '.$path, previous: $e);
        }

        if (! is_resource($stream)) {
            throw new RuntimeException('Media file not found or not readable: '.$path);
        }

        $localPath = self::temporaryPath($path);
        $handle = fopen($localPath, 'wb');

        if ($handle === false) {
            fclose($stream);

            throw new RuntimeException('Unable to create a temporary file for media upload.');
        }

        self::restrictPermissions($localPath);

        try {
            if (stream_copy_to_stream($stream, $handle) === false) {
                throw new RuntimeException('Unable to copy media file for upload: '.$path);
            }
        } catch (\Throwable $e) {
            self::remove($localPath);

            throw $e;
        } finally {
            fclose($handle);
            fclose($stream);
        }

        return $localPath;
    }

    private static function restrictPermissions(string $localPath): void
    {
        try {
            chmod($localPath, self::TEMPORARY_PERMISSIONS);
        } catch (\Throwable) {
            // Права зависят от файловой системы; содержимое всё равно удаляется после отправки.
        }
    }

    private static function remove(string $path): void
    {
        try {
            if (is_file($path)) {
                unlink($path);
            }
        } catch (\Throwable) {
            // Очистка временного файла best-effort: сбой не должен ронять отправку.
        }
    }

    private static function assertWithinSizeLimit(Filesystem $storage, string $path): void
    {
        $maxKb = config()->integer('filament-max-broadcasts.image.max_kb', 51200);

        if ($maxKb <= 0) {
            return;
        }

        if ($storage->size($path) > $maxKb * 1024) {
            throw new RuntimeException(sprintf(
                'Media file "%s" exceeds the configured limit of %d KB.',
                $path,
                $maxKb,
            ));
        }
    }

    private static function temporaryPath(string $storagePath): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($storagePath)) ?? '';
        $name = mb_substr($name !== '' ? $name : 'attachment', 0, 80);

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'max-broadcast-'.bin2hex(random_bytes(8)).'-'.$name;
    }
}
