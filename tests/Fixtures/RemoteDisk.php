<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Fixtures;

use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

/**
 * Двойник объектного хранилища: локального пути у файла нет, содержимое отдаётся потоком.
 */
final class RemoteDisk extends FilesystemAdapter
{
    public int $readStreamCalls = 0;

    /**
     * @param  array<string, string|null>  $files  null — файл есть, но поток недоступен
     */
    public function __construct(private readonly array $files = [])
    {
    }

    public function path(mixed $path): string
    {
        throw new RuntimeException('This driver does not support retrieving paths.');
    }

    public function exists(mixed $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function size(mixed $path): int
    {
        return strlen($this->files[$path] ?? '');
    }

    public function readStream(mixed $path): mixed
    {
        $this->readStreamCalls++;

        $contents = $this->files[$path] ?? null;

        if ($contents === null) {
            return null;
        }

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            return null;
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
