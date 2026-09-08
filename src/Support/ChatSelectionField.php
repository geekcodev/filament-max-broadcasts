<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Filament\Forms\Components\Select;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
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
            $options[self::chatIdOf($chat)] = self::labelFor($chat);
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
                $query->where('chat_id', 'like', "%{$search}%")
                    ->orWhereHas('maxUser', static function (Builder $q) use ($search): void {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            })
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            $options[self::chatIdOf($chat)] = self::labelFor($chat);
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

        $labels = [];

        foreach (self::activeChatsQuery()
            ->whereIn('chat_id', $values)
            ->limit(self::OPTION_LIMIT)
            ->get()
            ->unique('chat_id') as $chat) {
            $labels[self::chatIdOf($chat)] = self::labelFor($chat);
        }

        foreach ($values as $value) {
            if (! is_int($value) && ! is_string($value)) {
                continue;
            }

            $labels[$value] = $labels[$value] ?? e((string) $value);
        }

        return $labels;
    }

    public static function labelFor(Model $chat): string
    {
        $chatId = self::chatIdOf($chat);

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
        $labelKey = match (self::chatTypeValue($chat)) {
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
        return match (self::chatTypeValue($chat)) {
            'dialog' => 'success',
            'chat' => 'info',
            'channel' => 'warning',
            default => 'gray',
        };
    }

    public static function displayName(Model $chat): string
    {
        $title = $chat->getAttribute('title');
        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        if (self::chatTypeValue($chat) === ChatType::Dialog->value) {
            $user = $chat->getRelationValue('maxUser');

            if ($user instanceof Model) {
                $name = self::userDisplayName($user);

                if ($name !== '') {
                    return $name;
                }
            }
        }

        return (string) self::chatIdOf($chat);
    }

    /**
     * @return Builder<MaxChat>
     */
    private static function activeChatsQuery(): Builder
    {
        /** @var class-string<MaxChat> $chatsModel */
        $chatsModel = config('filament-max-broadcasts.chats_model', MaxChat::class);

        return $chatsModel::query()
            ->with('maxUser')
            ->where('status', MaxChatStatus::Active)
            ->orderByDesc('last_activity_at');
    }

    private static function chatTypeValue(Model $chat): string
    {
        $type = $chat->getAttribute('chat_type');

        if ($type instanceof ChatType) {
            return $type->value;
        }

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return 'unknown';
    }

    private static function userDisplayName(Model $user): string
    {
        $name = self::stringAttr($user, 'name');

        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                self::stringAttr($user, 'first_name'),
                self::stringAttr($user, 'last_name'),
            ], static fn (string $value): bool => $value !== '')));
        }

        if ($name === '') {
            return self::stringAttr($user, 'username');
        }

        return $name;
    }

    private static function chatIdOf(Model $chat): int
    {
        $chatId = $chat->getAttribute('chat_id');

        if (is_int($chatId)) {
            return $chatId;
        }

        if (is_string($chatId) && is_numeric($chatId)) {
            return (int) $chatId;
        }

        return 0;
    }

    private static function stringAttr(Model $model, string $key): string
    {
        $value = $model->getAttribute($key);

        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
