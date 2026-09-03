<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('max_broadcast_attachments', function (Blueprint $table): void {
            $table->comment('Вложения рассылки (картинки, видео, файлы) — по несколько на рассылку');
            $table->id();
            $table->foreignId('broadcast_id')
                ->comment('Рассылка, к которой относится вложение')
                ->constrained('max_broadcasts')->cascadeOnDelete();
            $table->string('upload_type', 16)->comment('Тип медиа для загрузки в MAX: image|video|audio|file');
            $table->string('path')->comment('Путь к файлу на диске (из конфига image.disk)');
            $table->unsignedInteger('sort_order')->default(0)->comment('Порядок вложения в сообщении');
            $table->timestamps();

            $table->index(['broadcast_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcast_attachments');
    }
};
