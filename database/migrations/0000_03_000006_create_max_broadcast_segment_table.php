<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('max_broadcast_segment')) {
            return;
        }

        Schema::create('max_broadcast_segment', function (Blueprint $table): void {
            $table->comment('Связь рассылки с сегментами получателей (многие-ко-многим)');
            $table->foreignId('broadcast_id')
                ->comment('Рассылка')
                ->constrained('max_broadcasts')->cascadeOnDelete();
            $table->foreignId('segment_id')
                ->comment('Сегмент получателей')
                ->constrained('max_broadcast_segments')->cascadeOnDelete();

            $table->primary(['broadcast_id', 'segment_id']);
            $table->index('segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcast_segment');
    }
};
