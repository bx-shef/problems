# shef.problems для разработчиков модулей линейки

Вход для программиста и его AI-агента: что модуль даёт соседям и где это
описано. Подробности не копируются — они в тематических файлах `docs/`. Карта
всей линейки — [toolsai/docs/dev/llms.txt](https://github.com/bx-shef/toolsai/blob/main/docs/dev/llms.txt).

## Что модуль даёт соседям

| что | точка входа | подробно |
|---|---|---|
| Логгер проблем: файл `<тип>.log` + журнал событий с типом и ответственным | `\Shef\Problems\Factory\SystemLoggerFactory::build()`, трейт `\Shef\Problems\Factory\Trait\LoggerProblems` | [logging.md](logging.md), [../4_monolog.md](../4_monolog.md) |
| Готовые логгеры (экран, файл, отладка) | enum `\Shef\Problems\Logger`, сервисы `shef.problems.*` в `ServiceLocator` | [../4_monolog.md](../4_monolog.md) |
| Типы проблем и ответственные из настроек | `\Shef\Problems\Main\Constants` | [api.md](api.md) |
| Каталог логов вне корня сайта | `Constants::getLogDir()`, `Constants::getLogFullPath()` | [../security.md](../security.md) |
| `\Throwable` → `\Bitrix\Main\Error` без аргументов вызовов в трассировке | `\Shef\Problems\Throwable\Manager::buildError()` | [../2_deffunctions.md](../2_deffunctions.md) |
| Быстрая отладка `_pr`, `_log`, `_log1` | `def-functions.php` | [../2_deffunctions.md](../2_deffunctions.md) |
| Обработчики событий ядра и меню «Учёт проблем» | `.settings.php` → `installEvents`, `admin/menu.php` | [../1_events.md](../1_events.md) |
| Уровни логирования: какой когда | `\Monolog\Level` | [../3_loglevel.md](../3_loglevel.md) |

## Файлы docs/dev

| файл | о чём |
|---|---|
| [logging.md](logging.md) | как модуль линейки пишет проблему и где её читают; рецепт мягкой зависимости |
| [api.md](api.md) | публичный API: классы, сигнатуры, константы, события; что не использовать |

## Ограничения

* Нужны `shef.options` 3.0.0+ и PHP 8.2+ (README, «Что нужно для установки»).
* REST-методов и контроллеров ajax у модуля нет.
* Своё событие для соседей одно — `shef.problems:onModuleUnInstall` при
  удалении модуля (шлётся, только если на него есть обработчики).
