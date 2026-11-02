<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Согласия должны переживать удаление сегмента (FK nullOnDelete), но колонка была NOT NULL,
     * из-за чего удаление сегмента с ответами падало. Новые установки создают колонку nullable
     * сразу (0000_03_000005), эта миграция приводит к тому же виду существующие.
     */
    public function up(): void
    {
        if (! Schema::hasTable('max_broadcast_consents') || ! Schema::hasColumn('max_broadcast_consents', 'segment_id')) {
            return;
        }

        foreach (Schema::getColumns('max_broadcast_consents') as $column) {
            if ($column['name'] === 'segment_id' && $column['nullable']) {
                return;
            }
        }

        Schema::table('max_broadcast_consents', function (Blueprint $table): void {
            $table->foreignId('segment_id')->nullable()
                ->comment('Сегмент согласий (max_broadcast_segments)')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('max_broadcast_consents') || ! Schema::hasColumn('max_broadcast_consents', 'segment_id')) {
            return;
        }

        // Возврат к NOT NULL невозможен, пока есть согласия удалённых сегментов: они удаляются.
        DB::table('max_broadcast_consents')->whereNull('segment_id')->delete();

        Schema::table('max_broadcast_consents', function (Blueprint $table): void {
            $table->foreignId('segment_id')->nullable(false)
                ->comment('Сегмент согласий (max_broadcast_segments)')
                ->change();
        });
    }
};
