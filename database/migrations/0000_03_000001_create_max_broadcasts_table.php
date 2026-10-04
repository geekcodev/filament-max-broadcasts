<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Полоса 0000_03 задана графом внешних ключей, а не вкусом: Laravel сортирует
 * миграции приложения и всех пакетов по имени файла и идёт по списку сверху вниз,
 * поэтому FK допустим только на таблицу, создаваемую миграцией с меньшим именем.
 * Полная раскладка полос — в 0000_00_000001_create_max_users_table пакета
 * laravel-max-client:
 *
 *   0000_00 — laravel-max-client: max_users, max_chats, max_chat_users
 *   0000_01 — приложение, системные таблицы (users)
 *   0000_02 — filament-max-chat
 *   0000_03 — этот пакет: max_broadcasts и производные
 *   0000_04 — приложение, доменные таблицы
 *
 * Отсюда позиция пакета: max_broadcasts.created_by и
 * max_broadcast_segments.created_by ссылаются на users (полоса 0000_01), поэтому
 * пакет обязан идти после системных миграций приложения.
 *
 * hasTable в up() обязателен во всех create-миграциях пакета: прежние имена
 * 0001_01_01_000001… уже записаны в таблицу migrations у установленных
 * приложений, поэтому после обновления новые имена числятся невыполненными при
 * уже существующих таблицах. Для max_broadcast_segments гвард заодно не даёт
 * повторно вставить стартовый сегмент.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('max_broadcasts')) {
            return;
        }

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
            $table->json('recipient_chat_ids')->nullable()
                ->comment('Выбранные chat_id получателей (JSON-массив; null — все из сегментов/все активные)');
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
