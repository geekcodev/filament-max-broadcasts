<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\AttachmentFile;
use GeekCo\FilamentMaxBroadcasts\Tests\Fixtures\RemoteDisk;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AttachmentFileTest extends TestCase
{
    public function testLocalDiskUsesOriginalPathWithoutCopy(): void
    {
        Storage::disk('local')->put('broadcasts/photo.jpg', 'fake');

        try {
            $file = AttachmentFile::fromDisk(Storage::disk('local'), 'broadcasts/photo.jpg');

            self::assertFalse($file->isTemporary());
            self::assertSame(Storage::disk('local')->path('broadcasts/photo.jpg'), $file->path());
            self::assertFileExists($file->path());

            $file->cleanup();

            self::assertFileExists($file->path());
        } finally {
            Storage::disk('local')->delete('broadcasts/photo.jpg');
        }
    }

    public function testLocalDiskThrowsWhenFileIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media file not found or not readable');

        AttachmentFile::fromDisk(Storage::disk('local'), 'broadcasts/missing.jpg');
    }

    public function testRemoteDiskDownloadsTemporaryFileWithOriginalName(): void
    {
        $disk = new RemoteDisk(['broadcasts/clip.mp4' => 'video-bytes']);

        $file = AttachmentFile::fromDisk($disk, 'broadcasts/clip.mp4');
        $path = $file->path();

        self::assertTrue($file->isTemporary());
        self::assertFileExists($path);
        self::assertStringContainsString('clip.mp4', $path);
        self::assertSame('video-bytes', file_get_contents($path));
        self::assertSame(1, $disk->readStreamCalls);

        $file->cleanup();

        self::assertFileDoesNotExist($path);
    }

    public function testRemoteDiskThrowsWhenFileIsMissing(): void
    {
        $disk = new RemoteDisk();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media file not found or not readable');

        AttachmentFile::fromDisk($disk, 'broadcasts/missing.mp4');
    }

    public function testRemoteDiskThrowsWhenStreamIsUnavailable(): void
    {
        $disk = new RemoteDisk(['broadcasts/clip.mp4' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media file not found or not readable');

        AttachmentFile::fromDisk($disk, 'broadcasts/clip.mp4');
    }

    public function testRemoteDiskThrowsWhenFileExceedsSizeLimit(): void
    {
        config()->set('filament-max-broadcasts.image.max_kb', 1);

        $disk = new RemoteDisk(['broadcasts/big.mp4' => str_repeat('a', 2048)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the configured limit');

        AttachmentFile::fromDisk($disk, 'broadcasts/big.mp4');
    }

    public function testBlankStoragePathThrows(): void
    {
        $disk = new RemoteDisk();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media file not found or not readable');

        AttachmentFile::fromDisk($disk, '   ');
    }
}
