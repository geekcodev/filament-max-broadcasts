<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Идентификаторы чатов и пользователей MAX знаковые: группы и каналы MAX имеют
 * отрицательные `chat_id`, а объявленная исторической миграцией `0001_01_01_000002`
 * колонка объявлена через `unsignedBigInteger`, и MySQL такие значения отвергает
 * (PostgreSQL беззнаковых типов не знает, там `bigint` знаковый изначально).
 *
 * Миграция правит существующие колонки на месте, а не создаёт новые: у хоста
 * таблица уже создана, и её структура объявлена прошлой миграцией (правило §2
 * AGENTS.md — переименовывать и править выполненные миграции нельзя).
 *
 * Схема собирается API фреймворка, а не сырым SQL, поэтому одна и та же миграция
 * применяется на MySQL и PostgreSQL: оба драйвера умеют `change()` без
 * doctrine/dbal. На SQLite целые не знаковые, и менять там нечего.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('max_broadcast_recipients', function (Blueprint $table): void {
            $table->bigInteger('user_id')
                ->nullable()
                ->comment('Id пользователя MAX (registry max_users)')
                ->change();
            $table->bigInteger('chat_id')
                ->nullable()
                ->comment('Chat_id чата получателя')
                ->change();
        });
    }

    public function down(): void
    {
        // Возврат к беззнаковым типам невозможен, если в таблице есть отрицательные
        // идентификаторы групп или каналов: база отвергла бы их при записи.
    }
};
