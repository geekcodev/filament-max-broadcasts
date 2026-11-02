<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature;

use GeekCo\FilamentMaxBroadcasts\Enums\BroadcastConsentAction;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastConsent;
use GeekCo\FilamentMaxBroadcasts\Models\BroadcastSegment;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Анонимная миграция Laravel объявляет up()/down() внутри файла, поэтому для статики
 * её форма описывается интерфейсом.
 */
interface MigrationWithUpAndDown
{
    public function up(): void;

    public function down(): void;
}

/**
 * Миграция 0000_03_000008 приводит существующие установки к той же схеме, что
 * создаёт 0000_03_000005 для новых: segment_id должен быть nullable, иначе
 * ON DELETE SET NULL не работает и удаление сегмента с согласиями падает.
 */
final class MakeConsentSegmentIdNullableTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../../database/migrations/'
        .'0000_03_000008_make_broadcast_consent_segment_id_nullable.php';

    public function testUpMakesColumnNullableAndKeepsHistoryOnSegmentDelete(): void
    {
        $this->createLegacyTable();

        $segment = $this->createSegment();
        $consent = $this->createConsent($segment->id);

        $this->migration()->up();

        self::assertTrue($this->segmentIdIsNullable());

        $segment->delete();

        $consent->refresh();

        self::assertNull($consent->segment_id);
    }

    public function testUpIsIdempotentWhenColumnIsAlreadyNullable(): void
    {
        $this->createLegacyTable();

        $segment = $this->createSegment();
        $this->createConsent($segment->id);

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        self::assertTrue($this->segmentIdIsNullable());
        self::assertSame(1, BroadcastConsent::query()->count());
    }

    public function testDownRemovesOrphansAndRestoresNotNull(): void
    {
        $this->createLegacyTable();

        $segment = $this->createSegment();
        $this->createConsent($segment->id);

        $migration = $this->migration();
        $migration->up();

        $segment->delete();

        $orphan = $this->createConsent(null);

        $migration->down();

        self::assertNull(BroadcastConsent::query()->find($orphan->getKey()));
        self::assertFalse($this->segmentIdIsNullable());
    }

    /**
     * Таблица в форме до исправления: segment_id объявлен NOT NULL при FK nullOnDelete.
     */
    private function createLegacyTable(): void
    {
        Schema::drop('max_broadcast_consents');

        Schema::create('max_broadcast_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('segment_id')
                ->comment('Сегмент согласий (max_broadcast_segments)')
                ->constrained('max_broadcast_segments')
                ->nullOnDelete();
            $table->bigInteger('chat_id')->comment('Chat_id получателя (MAX)');
            $table->bigInteger('user_id')->nullable()->comment('User_id получателя (MAX)');
            $table->string('action')->comment('Результат: opt_in или opt_out');
            $table->string('source')->default('callback')->comment('Источник факта согласия');
            $table->timestamps();

            $table->unique(['segment_id', 'chat_id']);
            $table->index(['segment_id', 'action']);
        });
    }

    /**
     * @return MigrationWithUpAndDown
     */
    private function migration(): object
    {
        /** @var MigrationWithUpAndDown $migration */
        $migration = require self::MIGRATION;

        return $migration;
    }

    private function createSegment(): BroadcastSegment
    {
        return BroadcastSegment::query()->create([
            'name' => 'Сегмент согласий',
            'chat_ids' => [11],
        ]);
    }

    private function createConsent(?int $segmentId): BroadcastConsent
    {
        return BroadcastConsent::query()->create([
            'segment_id' => $segmentId,
            'chat_id' => 11,
            'action' => BroadcastConsentAction::OptIn,
        ]);
    }

    private function segmentIdIsNullable(): bool
    {
        foreach (Schema::getColumns('max_broadcast_consents') as $column) {
            if ($column['name'] === 'segment_id') {
                return $column['nullable'];
            }
        }

        self::fail('Колонка segment_id не найдена.');
    }
}
