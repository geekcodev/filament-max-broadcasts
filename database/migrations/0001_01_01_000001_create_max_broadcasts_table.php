<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('max_broadcasts', function (Blueprint $table): void {
            $table->comment('Массовые рассылки пользователям MAX-мессенджера');
            $table->id();
            $table->text('text')->comment('HTML-текст рассылки (санитизируется при создании и перед отправкой)');
            $table->string('type', 16)->default('news')->comment('Тип рассылки — значение из реестра config types (по умолчанию news|promo)');
            $table->string('status', 16)->comment('Статус рассылки: scheduled|running|completed|cancelled|failed');
            $table->timestamp('scheduled_at')->nullable()->comment('Отложенная отправка; null — сразу');
            $table->timestamp('sent_at')->nullable()->comment('Фактическое время начала отправки');
            $table->unsignedInteger('total_recipients')->default(0)->comment('Общее число получателей');
            $table->unsignedInteger('delivered_count')->default(0)->comment('Доставлено');
            $table->unsignedInteger('failed_count')->default(0)->comment('Не доставлено');
            $table->foreignId('created_by')->nullable()
                ->comment('Автор рассылки (модель user_model)')
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcasts');
    }
};
