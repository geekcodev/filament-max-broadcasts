<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use GeekCo\LaravelMaxClient\Enums\MaxChatStatus;
use GeekCo\LaravelMaxClient\Models\MaxChat;
use GeekCo\MaxPhpClient\Enum\ChatType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChatSelectionField
{
    private const OPTION_LIMIT = 50;

    private const LABEL_CHUNK = 500;

    /**
     * @param  string|null  $helperText
     */
    public static function make(
        string $statePath,
        string $label,
        ?string $helperText = null,
        bool $required = false,
        bool $dialogsOnly = false,
    ): Select {
        $field = Select::make($statePath)
            ->label($label)
            ->helperText($helperText)
            ->multiple()
            ->searchable()
            ->options(static fn (): array => self::options($dialogsOnly))
            ->getSearchResultsUsing(static fn (string $search): array => self::searchResults($search, $dialogsOnly))
            ->getOptionLabelsUsing(static fn (array $values): array => self::optionLabels($values))
            ->allowHtml()
            ->required($required)
            ->columnSpanFull()
            ->hintAction(
                Action::make('select_all')
                    ->label(__('filament-max-broadcasts::broadcasts.chat_selection.select_all'))
                    ->link()
                    ->action(static function (Set $set) use ($statePath, $dialogsOnly): void {
                        $set($statePath, self::allIds($dialogsOnly));
                    }),
            );

        if ($dialogsOnly) {
            $field->rule(new DialogsOnlyRule());
        }

        return $field;
    }

    /**
     * @return array<int, string>
     */
    public static function options(bool $dialogsOnly = false): array
    {
        $options = [];

        foreach (self::activeChatsQuery($dialogsOnly)
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
    public static function searchResults(string $search, bool $dialogsOnly = false): array
    {
        if (trim($search) === '') {
            return [];
        }

        $options = [];

        foreach (self::activeChatsQuery($dialogsOnly)
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
     * Идентификаторы всех активных чатов (для действия «Выбрать всех»), без лимита вариантов.
     *
     * @return list<int>
     */
    public static function allIds(bool $dialogsOnly = false): array
    {
        $chatsModel = ChatRegistry::model();

        $query = $chatsModel::query()->where('status', MaxChatStatus::Active);

        if ($dialogsOnly) {
            $query->where('chat_type', ChatType::Dialog->value);
        }

        return array_values(array_unique(array_map(
            static fn (mixed $chatId): int => (int) (is_scalar($chatId) ? $chatId : 0),
            $query->orderByDesc('last_activity_at')->pluck('chat_id')->all(),
        )));
    }

    /**
     * Подписи выбранных значений без лимита вариантов: после «Выбрать всех» все значения
     * выводятся полностью, а не сырыми ID.
     *
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

        foreach (array_chunk($ids, self::LABEL_CHUNK) as $chunk) {
            foreach (self::activeChatsQuery()
                ->whereIn('chat_id', $chunk)
                ->get()
                ->unique('chat_id') as $chat) {
                $labels[ChatRegistry::chatId($chat)] = self::labelFor($chat);
            }
        }

        foreach ($ids as $value) {
            $labels[$value] = $labels[$value] ?? e((string) $value);
        }

        return $labels;
    }

    public static function labelFor(Model $chat): string
    {
        $chatId = ChatRegistry::chatId($chat);

        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:.5rem;min-width:0">%s<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">%s</span><span style="color:var(--gray-400);flex-shrink:0">(ID: %d)</span></span>',
            self::typeBadge($chat),
            e(self::displayName($chat)),
            $chatId,
        );
    }

    public static function typeBadge(Model $chat): string
    {
        return sprintf(
            '<span class="fi-badge fi-size-sm" style="background-color:var(--%s-50);color:var(--%s-700)">%s</span>',
            self::chatTypeColor($chat),
            self::chatTypeColor($chat),
            e(self::chatTypeLabel($chat)),
        );
    }

    public static function idBadge(int $id): string
    {
        return sprintf(
            '<span class="fi-badge fi-size-sm" style="background-color:var(--gray-50);color:var(--gray-700)">ID: %d</span>',
            $id,
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
     * Среди переданных chat_id — те, у которых в реестре тип «диалог» (статус не учитывается:
     * тип чата не меняется, а проверка нужна для сохранения сегмента).
     *
     * @param  array<mixed>  $ids
     * @return list<int>
     */
    public static function dialogIdsAmong(array $ids): array
    {
        $ids = array_values(array_map('intval', array_filter($ids, is_scalar(...))));

        if ($ids === []) {
            return [];
        }

        $chatsModel = ChatRegistry::model();

        $result = [];

        foreach (array_chunk($ids, self::LABEL_CHUNK) as $chunk) {
            /** @var int|string $chatId */
            foreach ($chatsModel::query()
                ->whereIn('chat_id', $chunk)
                ->where('chat_type', ChatType::Dialog->value)
                ->pluck('chat_id') as $chatId) {
                $result[] = (int) $chatId;
            }
        }

        return $result;
    }

    /**
     * @return Builder<MaxChat>
     */
    private static function activeChatsQuery(bool $dialogsOnly = false): Builder
    {
        $chatsModel = ChatRegistry::model();

        $query = ChatRegistry::withUsers(
            $chatsModel::query()
                ->where('status', MaxChatStatus::Active)
                ->orderByDesc('last_activity_at'),
        );

        if ($dialogsOnly) {
            $query->where('chat_type', ChatType::Dialog->value);
        }

        return $query;
    }
}
