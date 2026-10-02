<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Filament\Forms\Components\Select;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            ->options(static fn (): array => self::options())
            ->getSearchResultsUsing(static fn (string $search): array => self::searchResults($search))
            ->getOptionLabelsUsing(static fn (array $values): array => self::optionLabels($values))
            ->allowHtml()
            ->required($required)
            ->columnSpanFull();
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::activeChatsQuery()
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            $options[ChatRegistry::chatId($chat)] = self::labelFor($chat);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function searchResults(string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        $options = [];

        foreach (self::activeChatsQuery()
            ->where(static function (Builder $query) use ($search): void {
                $query->where('chat_id', 'like', "%{$search}%");
                ChatRegistry::whereUserLike($query, $search);
            })
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            $options[ChatRegistry::chatId($chat)] = self::labelFor($chat);
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int|string, string>
     */
    public static function optionLabels(array $values): array
    {
        $ids = array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_int($value) || is_string($value),
        ));

        if ($ids === []) {
            return [];
        }

        $labels = [];

        foreach (self::activeChatsQuery()
            ->whereIn('chat_id', $ids)
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            $labels[ChatRegistry::chatId($chat)] = self::labelFor($chat);
        }

        foreach ($ids as $value) {
            $labels[$value] = $labels[$value] ?? e((string) $value);
        }

        return $labels;
    }

    public static function labelFor(Model $chat): string
    {
        $chatId = ChatRegistry::chatId($chat);

        $badge = sprintf(
            '<span class="fi-badge fi-size-sm" style="background-color:var(--%s-50);color:var(--%s-700)">%s</span>',
            self::chatTypeColor($chat),
            self::chatTypeColor($chat),
            e(self::chatTypeLabel($chat)),
        );

        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:.5rem;min-width:0">%s<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">%s</span><span style="color:var(--gray-400);flex-shrink:0">(ID: %d)</span></span>',
            $badge,
            e(self::displayName($chat)),
            $chatId,
        );
    }

    public static function chatTypeLabel(Model $chat): string
    {
        $labelKey = match (ChatRegistry::chatType($chat)) {
            'dialog' => 'dialog',
            'chat' => 'chat',
            'channel' => 'channel',
            default => 'unknown',
        };

        /** @var string $label */
        $label = __("filament-max-broadcasts::broadcasts.chat_types.{$labelKey}");

        return $label;
    }

    public static function chatTypeColor(Model $chat): string
    {
        return match (ChatRegistry::chatType($chat)) {
            'dialog' => 'success',
            'chat' => 'info',
            'channel' => 'warning',
            default => 'gray',
        };
    }

    public static function displayName(Model $chat): string
    {
        return ChatRegistry::displayName($chat);
    }

    /**
     * @return Builder<MaxChat>
     */
    private static function activeChatsQuery(): Builder
    {
        $chatsModel = ChatRegistry::model();

        return ChatRegistry::withUsers(
            $chatsModel::query()
                ->where('status', MaxChatStatus::Active)
                ->orderByDesc('last_activity_at'),
        );
    }
}
