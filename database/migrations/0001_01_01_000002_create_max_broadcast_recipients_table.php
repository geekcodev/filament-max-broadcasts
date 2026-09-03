<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('max_broadcast_recipients', function (Blueprint $table): void {
            $table->comment('Получатели конкретной рассылки (снимок на момент отправки)');
            $table->id();
            $table->foreignId('broadcast_id')->constrained('max_broadcasts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Id пользователя MAX (registry max_users)');
            $table->unsignedBigInteger('chat_id')->nullable()->comment('Chat_id чата получателя');
            $table->string('status', 16)->comment('Статус доставки получателю: pending|sent|failed');
            $table->text('error')->nullable()->comment('Сообщение об ошибке (без чувствительных данных)');
            $table->timestamp('sent_at')->nullable()->comment('Момент отправки');
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
            $table->index(['broadcast_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcast_recipients');
    }
};
