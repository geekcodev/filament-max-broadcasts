<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Support;

use GeekCo\FilamentMaxBroadcasts\Support\AttachmentStorage;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class AttachmentStorageTest extends TestCase
{
    public function testDefaultDiskIsPrivateLocal(): void
    {
        self::assertSame('local', AttachmentStorage::disk());
        self::assertFalse(AttachmentStorage::isPublicDisk());
    }

    public function testUsesConfiguredDisk(): void
    {
        Storage::fake('public');
        config()->set('filament-max-broadcasts.image.disk', 'public');

        self::assertSame('public', AttachmentStorage::disk());
        self::assertTrue(AttachmentStorage::isPublicDisk());
    }

    public function testEmptyConfiguredDiskFallsBackToDefault(): void
    {
        config()->set('filament-max-broadcasts.image.disk', '  ');

        self::assertSame('local', AttachmentStorage::disk());
    }

    public function testUnknownDiskIsTreatedAsPrivate(): void
    {
        config()->set('filament-max-broadcasts.image.disk', 'no-such-disk');

        self::assertFalse(AttachmentStorage::isPublicDisk());
    }

    public function testObjectStorageDiskIsNeverPublic(): void
    {
        config()->set('filesystems.disks.s3-test', [
            'driver' => 's3',
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'region',
            'bucket' => 'bucket',
            'url' => 'https://cdn.example',
        ]);
        config()->set('filament-max-broadcasts.image.disk', 's3-test');

        self::assertFalse(AttachmentStorage::isPublicDisk());
    }

    public function testLocalDiskWithPublicVisibilityIsPublic(): void
    {
        config()->set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
            'url' => '/storage',
        ]);
        config()->set('filament-max-broadcasts.image.disk', 'uploads');

        self::assertTrue(AttachmentStorage::isPublicDisk());
    }

    public function testPublicDiskUrlIsReturnedAsIs(): void
    {
        Storage::fake('public');
        config()->set('filament-max-broadcasts.image.disk', 'public');
        Storage::disk('public')->put('broadcasts/photo.jpg', 'fake');

        $expected = trim((string) Storage::disk('public')->url('broadcasts/photo.jpg'));

        self::assertSame($expected, AttachmentStorage::url('broadcasts/photo.jpg'));
    }

    public function testPrivateDiskUrlIsTemporaryAndSigned(): void
    {
        Storage::disk('local')->put('broadcasts/photo.jpg', 'fake');

        try {
            $url = AttachmentStorage::url('broadcasts/photo.jpg');

            self::assertIsString($url);
            self::assertStringContainsString('/storage/broadcasts/photo.jpg', (string) $url);
            self::assertStringContainsString('expires=', (string) $url);
            self::assertStringContainsString('signature=', (string) $url);
        } finally {
            Storage::disk('local')->delete('broadcasts/photo.jpg');
        }
    }

    public function testMissingFileHasNoUrl(): void
    {
        self::assertNull(AttachmentStorage::url('broadcasts/missing.jpg'));
    }

    public function testBlankPathHasNoUrl(): void
    {
        self::assertNull(AttachmentStorage::url('   '));
    }

    public function testDiskWithoutTemporaryUrlsHasNoUrl(): void
    {
        $root = storage_path('framework/testing/disks/private-no-serve');

        config()->set('filesystems.disks.private-no-serve', [
            'driver' => 'local',
            'root' => $root,
        ]);
        config()->set('filament-max-broadcasts.image.disk', 'private-no-serve');

        Storage::disk('private-no-serve')->put('broadcasts/photo.jpg', 'fake');

        try {
            self::assertNull(AttachmentStorage::url('broadcasts/photo.jpg'));
        } finally {
            Storage::disk('private-no-serve')->deleteDirectory('broadcasts');
        }
    }
}
