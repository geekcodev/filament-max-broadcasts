<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Filament\Forms\Components\Select;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;

class ChatSelectionField
{
    private const OPTION_LIMIT = 50;

    public static function make(
        string $statePath,
        string $label,
        ?string $helperText = null,
        bool $required = false,
    ): Select {
        return Select::make($statePath)
            ->label($label)
            ->helperText($helperText)
            ->multiple()
            ->searchable()
            ->options([])
            ->getSearchResultsUsing(static fn (string $search): array => self::searchResults($search))
            ->getOptionLabelsUsing(static fn (array $values): array => self::optionLabels($values))
            ->required($required)
            ->columnSpanFull();
    }

    /**
     * @return array<int, string>
     */
    public static function searchResults(string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        $options = [];

        foreach ($chatsModel::query()
            ->where('status', MaxChatStatus::Active)
            ->where('chat_id', 'like', "%{$search}%")
            ->orderByDesc('last_activity_at')
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            /** @var int $chatId */
            $chatId = $chat->getAttribute('chat_id');
            /** @var string $title */
            $title = $chat->getAttribute('title') ?? $chat->getAttribute('chat_id');

            $options[$chatId] = sprintf('%s (ID: %s)', $title, $chatId);
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int|string, string>
     */
    public static function optionLabels(array $values): array
    {
        if ($values === []) {
            return [];
        }

        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        $labels = [];

        foreach ($chatsModel::query()
            ->where('status', MaxChatStatus::Active)
            ->whereIn('chat_id', $values)
            ->orderByDesc('last_activity_at')
            ->get()
            ->unique('chat_id') as $chat) {
            /** @var int $chatId */
            $chatId = $chat->getAttribute('chat_id');
            /** @var string $title */
            $title = $chat->getAttribute('title') ?? $chat->getAttribute('chat_id');

            $labels[$chatId] = sprintf('%s (ID: %s)', $title, $chatId);
        }

        foreach ($values as $value) {
            if (! is_int($value) && ! is_string($value)) {
                continue;
            }

            $labels[$value] = $labels[$value] ?? (string) $value;
        }

        return $labels;
    }
}
