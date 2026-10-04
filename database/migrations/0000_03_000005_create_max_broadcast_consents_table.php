<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('max_broadcast_consents')) {
            return;
        }

        Schema::create('max_broadcast_consents', function (Blueprint $table): void {
            $table->comment('Согласия получателей на рассылку (opt-in/opt-out через callback-кнопки)');
            $table->id();
            $table->foreignId('segment_id')->comment('Сегмент согласий (max_broadcast_segments)')
                ->constrained('max_broadcast_segments')->nullOnDelete();
            $table->bigInteger('chat_id')->comment('Chat_id получателя (MAX)');
            $table->bigInteger('user_id')->nullable()->comment('User_id получателя (MAX)');
            $table->string('action')->comment('Результат: opt_in или opt_out');
            $table->string('source')->default('callback')->comment('Источник факта согласия');
            $table->timestamps();

            $table->unique(['segment_id', 'chat_id']);
            $table->index(['segment_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcast_consents');
    }
};
