---
tags: [ ui, chat-selection, badge ]
date: 2026-09-07
---

# Бейдж типа чата + имя + ID в выборе получателей, список чатов при открытии

Контекст: пользователь заметил два неудобства в селекте выбора получателей (общий `Support\ChatSelectionField`,
используется в сегментах и в форме рассылки): (1) в опции дублируется id («id (ID: id)»), хочется бейдж типа чата +
имя + `(ID: …)`; (2) при открытии селекта никакого списка — только строка поиска и пустая область, хочется видеть список
получателей.

## Что сделано

`src/Support/ChatSelectionField.php`:

- `options([])` → `options(static fn (): array => self::options())`. `options()` возвращает активные чаты по
  `last_activity_at DESC` (лимит `OPTION_LIMIT = 50`, дедуп `unique('chat_id')`, eager `with('maxUser')`). В Filament v5
  `options` как Closure даёт `hasDynamicOptions() === true`, и JS-компонент (`select.js` → `openDropdown`, метод
  `getOptionsUsing`) **подгружает опции на каждом открытии** и рендерит их — поэтому список получателей теперь виден
  сразу, а поиск (`getSearchResultsUsing`) дополнительно фильтрует серверно.
- Метка опции — `labelFor(Model $chat): string`:
    - бейдж типа:
      `<span class="fi-badge fi-size-sm" style="background-color:var(--{color}-50);color:var(--{color}-700)">`, цвет по
      типу: dialog→success, chat→info, channel→warning, иначе gray; подпись из
      `lang broadcasts.chat_types.{dialog,chat,channel,unknown}`;
    - имя: `title` из переопределённого `chats_model` → для диалога `maxUser` (`name` → first+last → `username`) →
      фолбэк `chat_id` (в `max_chats` laravel-max-client названий групп/каналов нет — хранится только профиль
      `max_users`);
    - хвост `<span style="color:var(--gray-400)">(ID: {chat_id})</span>`; строка собрана с `e()` (имя, подпись типа) —
      XSS-безопасно.
- `->allowHtml()` на селекте: Filament рендерит label через `innerHTML` в опциях и в выбранных пилюлях
  (`isHtmlAllowed`).
- Поиск расширен именем: `chat_id LIKE` OR `orWhereHas('maxUser')` по first_name/last_name/username/name.
- PHPStan-safe хелперы `chatIdOf(Model): int` и `stringAttr(Model, string): string` вместо кастов `mixed` (level max).

`lang/ru/broadcasts.php`, `lang/en/broadcasts.php`: добавлен блок `chat_types`.

`tests/Unit/Support/ChatSelectionFieldTest.php`: переписан под новую метку и поведение (locale принудительно `ru`):

- `options()` — только активные, порядок по убыванию активности, дедуп по `chat_id`;
- поиск по `chat_id` и по имени (`Иван` → chat 22), stopped исключён, пустой поиск → `[]`;
- дедуп поиска; `optionLabels` (резолв + raw-фолбэк для отсутствующих); пустой выбор;
- `labelFor`: диалог → бейдж «Диалог» (fi-badge) + «Иван Петров» + `(ID: 11)`; группа → бейдж с `var(--info-50)` и
  фолбэк имени `22`.

## Решения и отклонения

- **Название для групп/каналов**: реестр `max_chats` не хранит title/тему группы/канала (только user_id, chat_id,
  status, chat_type, last_activity_at), поэтому для них имени нет — честный фолбэк `chat_id`. Если хост переопределит
  `chats_model` с полем `title`, оно будет показано (учтено первым приоритетом).
- **Бейдж через инлайн-цвета**, а не Tailwind-классы `fi-color-*`: классы цветов компилируются в Tailwind приложения и в
  рантайм-инжект строкой из PHP не гарантированы; `fi-badge`/`fi-size-sm` — структурные и есть в
  `filament/filament/dist/theme.css`. CSS-переменные `--{color}-50/700`, `--gray-400` штатно генерируются темой
  Filament.
- **Поведение пилюль**: выбранные чаты в dropdown скрываются (Filament фильтрует выбранные в multiple), метки уже
  выбранных приходят из `getOptionLabelsUsing` — единый `labelFor`.
- **Аудит**: первый запуск `composer audit` упал на сетевом glitch packagist (SSL timeout) — повторён с
  `--ignore-unreachable`, уязвимостей нет.

## Тесты и Gate

Итог: **151 тест / 419 assertions** (было 148/410). Gate: lint 0, analyse 0, test 151 (419 assertions), audit 0
критичных.

PHPUnit: OK (151 tests, 419 assertions), время ~30 с.

## Доп. аудит production-grade (вопрос пользователя)

Проверка изменений против AGENTS.md/OWASP выявила 2 замечания, исправлены + добавлены тесты (итог 153/423):

- **XSS (A03)**: `optionLabels()` для выбранных значений, отсутствующих в реестре, возвращал сырое значение, а
  `allowHtml()` (`canAllowHtml` → `innerHTML` в select.js) рендерит label как HTML. Livewire-state можно подделать →
  persisted-reflected XSS в пилюлях селекта (включая view-страницу рассылки, где рисуются те же метки). Исправлено
  `e((string) $value)`; тест `testOptionLabelsEscapesRawFallbackValue` (`<script>alert(1)</script>` → экранирован). Ключ
  не экранируется (он не рендерится как HTML — только его label-значение).
- **Сырой `chat_type` в ключе lang**: `labelFor` использовал `__("...chat_types.{$typeValue}")` с raw-значением из БД —
  при нестандартном типе вывел бы сам ключ с raw-строкой. Нормализовано: общий `match` выдаёт `[color, labelKey]`,
  неизвестные → `unknown` («Чат»/gray). Тест `testLabelFallsBackToUnknownType` через модель-фикстуру
  `StringChatTypeChat`
  (наследник `MaxChat` без enum-каста `chat_type`) — стандартный `MaxChat` кастит `chat_type` в `ChatType`-enum, и
  неизвестное значение упало бы ValueError ещё до `chatTypeValue()`; ветка `unknown` — defensive для переопределяемой
  `chats_model`. Выяснено по vendor (`MaxChat::casts()`), что для пакетной модели реально достижимы только dialog/chat/
  channel.

Остальное подтверждено: `e()` на имени и подписи типа, числовой ID через `%d`, цвета из закрытого `match`; источник
данных — серверный query (A04), поле за правами `permissions.create/manage` (A01); `declare(strict_types=1)`, PHPStan
level max 0 ошибок, lint 0. Несоответствий AGENTS.md не найдено.