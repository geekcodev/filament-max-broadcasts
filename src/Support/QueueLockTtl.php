<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

/**
 * TTL лока очереди: не меньше таймаута джобы плюс минута запаса.
 *
 * `queue.lock_ttl_seconds` задаёт нижнюю границу, а не точное значение: иначе
 * на больших аудиториях лок истекает раньше, чем джоба закончит работу, и
 * второй воркер берётся за ту же отправку.
 */
final class QueueLockTtl
{
    private const int BUFFER_SECONDS = 60;

    public static function seconds(): int
    {
        $configured = config()->integer('filament-max-broadcasts.queue.lock_ttl_seconds', 600);
        $timeout = config()->integer('filament-max-broadcasts.queue.timeout', 3600);

        return max($configured, $timeout) + self::BUFFER_SECONDS;
    }
}
