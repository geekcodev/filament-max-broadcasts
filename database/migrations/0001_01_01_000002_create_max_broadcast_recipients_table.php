<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('max_broadcast_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('max_broadcasts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->string('status', 8);
            $table->string('error', 191)->nullable();
            $table->timestamp('sent_at')->nullable();
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
