---
tags: [ types, contract, config ]
date: 2026-09-03
---

# Типы рассылок: убраны лимиты из BroadcastTypeContract

- Сделано: `maxTextLength()`/`imageMaxKb()` удалены из `BroadcastTypeContract`, трейта `BroadcastTypeDefaults` и реестра
  `BroadcastTypes` (YAGNI — не варьируются по типу: текст 4000 = константа API MAX, фото = `image.max_kb` из конфига).
  Форма: `maxLength(4000)` и `maxSize(config image.max_kb)` статично, убран `->live()` на select и helper
  `selectedType()`
  (импорт `Get`). Лимиты остались глобальными. Синхронизированы README/AGENTS.md/config/.env.example; сессия дополнена.
- Решения: верный аргумент пользователя — per-type лимиты это лишняя абстракция; контракт сокращён до реальных отличий
  поведения типов (подписи/кнопки/цвет).
- Gate: lint 0, analyse 0, test 77 (205 assertions), audit 0 критичных.
