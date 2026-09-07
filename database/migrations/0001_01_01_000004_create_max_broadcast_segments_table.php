<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('max_broadcast_segments', function (Blueprint $table): void {
            $table->comment('Именованные сегменты получателей для массовых рассылок');
            $table->id();
            $table->string('name')->comment('Название сегмента');
            $table->text('description')->nullable()->comment('Описание сегмента');
            $table->json('chat_ids')->comment('Список chat_id получателей (JSON-массив целых чисел)');
            $table->foreignId('created_by')->nullable()
                ->comment('Автор сегмента (модель user_model)')
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        $name = config('filament-max-broadcasts.consent.segment_name', 'Новости и акции');

        DB::table('max_broadcast_segments')->insert([
            'name' => $name,
            'description' => 'Первичный сегмент согласия: получатели новостей и акций',
            'chat_ids' => '[]',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('max_broadcast_segments');
    }
};
