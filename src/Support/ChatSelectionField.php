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
    /**
     * Служебное значение варианта «Выбрать всех» внутри селекта: при сохранении
     * разворачивается в список всех активных чатов (см. dehydrateStateUsing).
     */
    public const string SELECT_ALL = '__select_all__';

    private const OPTION_LIMIT = 50;

    private const LABEL_CHUNK = 500;

    /**
     * @param  string|null  $helperText
     *
     * @throws \InvalidArgumentException если одновременно заданы dialogsOnly и nonDialogsOnly
     */
    public static function make(
        string $statePath,
        string $label,
        ?string $helperText = null,
        bool $required = false,
        bool $dialogsOnly = false,
        bool $nonDialogsOnly = false,
        bool $withSelectAll = true,
    ): Select {
        if ($dialogsOnly && $nonDialogsOnly) {
            throw new \InvalidArgumentException('Chat selection cannot be both dialogs-only and non-dialogs-only.');
        }

        $field = Select::make($statePath)
            ->label($label)
            ->helperText($helperText)
            ->multiple()
            ->searchable()
            ->options(static fn (): array => self::options($dialogsOnly, $nonDialogsOnly, $withSelectAll))
            ->getSearchResultsUsing(
                static fn (string $search): array => self::searchResults($search, $dialogsOnly, $nonDialogsOnly),
            )
            ->getOptionLabelsUsing(static fn (array $values): array => self::optionLabels($values))
            ->dehydrateStateUsing(
                static fn (mixed $state): array => self::expandState($state, $dialogsOnly, $nonDialogsOnly),
            )
            ->allowHtml()
            ->required($required)
            ->columnSpanFull();

        if ($dialogsOnly) {
            $field->rule(new DialogsOnlyRule());
        }

        return $field;
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(
        bool $dialogsOnly = false,
        bool $nonDialogsOnly = false,
        bool $withSelectAll = true,
    ): array {
        $options = [];

        if ($withSelectAll) {
            $options[self::SELECT_ALL] = self::selectAllLabel();
        }

        foreach (self::activeChatsQuery($dialogsOnly, $nonDialogsOnly)
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
    public static function searchResults(
        string $search,
        bool $dialogsOnly = false,
        bool $nonDialogsOnly = false,
    ): array {
        if (trim($search) === '') {
            return [];
        }

        $options = [];

        foreach (self::activeChatsQuery($dialogsOnly, $nonDialogsOnly)
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
    public static function allIds(bool $dialogsOnly = false, bool $nonDialogsOnly = false): array
    {
        $chatsModel = ChatRegistry::model();

        $query = $chatsModel::query()->where('status', MaxChatStatus::Active);

        if ($dialogsOnly) {
            $query->where('chat_type', ChatType::Dialog->value);
        } elseif ($nonDialogsOnly) {
            $query->whereIn('chat_type', [ChatType::Chat->value, ChatType::Channel->value]);
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

        if (in_array(self::SELECT_ALL, $ids, true)) {
            $labels[self::SELECT_ALL] = self::selectAllLabel();
        }

        $ids = array_values(array_filter(
            $ids,
            static fn (int|string $value): bool => $value !== self::SELECT_ALL,
        ));

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

    /**
     * Раскрывает служебное значение «Выбрать всех» в список чатов.
     *
     * @return list<int>
     */
    public static function expandState(
        mixed $state,
        bool $dialogsOnly = false,
        bool $nonDialogsOnly = false,
    ): array {
        $values = is_array($state) ? $state : [];

        $selectAll = false;
        $ids = [];

        foreach ($values as $value) {
            if ($value === self::SELECT_ALL) {
                $selectAll = true;

                continue;
            }

            if (is_int($value) || (is_string($value) && is_numeric($value))) {
                $ids[] = (int) $value;
            }
        }

        if ($selectAll) {
            return self::allIds($dialogsOnly, $nonDialogsOnly);
        }

        return array_values(array_unique($ids));
    }

    private static function selectAllLabel(): string
    {
        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600;color:var(--primary-600)">%s</span>',
            e(__('filament-max-broadcasts::broadcasts.chat_selection.select_all')),
        );
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
    private static function activeChatsQuery(bool $dialogsOnly = false, bool $nonDialogsOnly = false): Builder
    {
        $chatsModel = ChatRegistry::model();

        $query = ChatRegistry::withUsers(
            $chatsModel::query()
                ->where('status', MaxChatStatus::Active)
                ->orderByDesc('last_activity_at'),
        );

        if ($dialogsOnly) {
            $query->where('chat_type', ChatType::Dialog->value);
        } elseif ($nonDialogsOnly) {
            $query->whereIn('chat_type', [ChatType::Chat->value, ChatType::Channel->value]);
        }

        return $query;
    }
}
