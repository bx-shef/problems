# Как модуль линейки пишет и читает проблемы

## Записать проблему

Проблема — запись, которая ложится одновременно в файл `<тип в нижнем
регистре>.log` каталога логов и в журнал событий Битрикса с типом
(`AUDIT_TYPE_ID`) и ответственным. Строит её
`\Shef\Problems\Factory\SystemLoggerFactory::build()`:

```php
public static function build(
    \Monolog\Level $logLevel,
    string $auditType = Constants::AuditTypeProblem,   // SH_PROBLEMS_PROBLEM
    string $moduleId = Constants::EmptyValue,          // 'empty'
    string $className = Constants::EmptyValue,
    null|int $assigned = null                          // null → Constants::getDefUserId()
): \Shef\Problems\Integration\Monolog\Logger
```

* `$auditType` не из `Constants::getAuditTypeList()` подменяется на
  `SH_PROBLEMS_PROBLEM`, без ошибки.
* Файл пишет `CappedStreamHandler` (с потолком размера), журнал —
  `BitrixCEventLogHandler`; на логгере `TraceProcessor` (трассировка в `extra.trace`).
* Записи ниже `$logLevel` не пишутся никуда.

### Рецепт для модуля линейки: мягкая зависимость

Так устроен `\Shef\ToolsAi\Container::getLogger()` в shef.toolsai: нет модуля
или сбой — `null`, вызывающий код не падает.

```php
public static function getLogger(string $className): ?\Psr\Log\LoggerInterface
{
    try
    {
        if(!\Bitrix\Main\Loader::includeModule('shef.problems'))
        {
            return null;
        }

        return \Shef\Problems\Factory\SystemLoggerFactory::build(
            logLevel: \Monolog\Level::Warning,
            auditType: \Shef\Problems\Main\Constants::AuditTypeSale,
            moduleId: 'shef.toolsai',
            className: $className,
            assigned: \Shef\Problems\Main\Constants::getSaleUserId(),
        );
    }
    catch(\Throwable)
    {
        return null;
    }
}

$logger?->error('Провайдер не ответил', ['itemId' => $taskId]);
```

Только журнал событий, без файла: убрать файловые обработчики через
`$logger->setHandlers(...)` (в shef.toolsai — `\Shef\ToolsAi\Log\Handlers::withoutFiles()`).

### Жёсткая зависимость: трейт

Модуль, который требует shef.problems, подключает трейт
`\Shef\Problems\Factory\Trait\LoggerProblems`: обязательные
`getClassName()`, `getModuleId()`; необязательные `getLogLevel()` (умолчание
`Info`), `getAuditType()` (`SH_PROBLEMS_PROBLEM`), `getAssignedId()`
(`Constants::getDefUserId()`). `initLogger()` кладёт логгер в `$this->logger`,
`configureLogger()` подменяет его (для тестов). Пример — [../4_monolog.md](../4_monolog.md),
запускаемый — `examples/problems.php`.

## Что передавать в логгер

Первым аргументом — строку, `\Stringable`, `\Throwable`, `\Bitrix\Main\Error`,
`\Bitrix\Main\Result`, массив, `Arrayable`, `Jsonable`, `\JsonSerializable`
(таблица — [../4_monolog.md](../4_monolog.md)). Иное — `InvalidArgumentException`:
это ошибка вызова, она бросается. Ключи контекста `itemId` и `moduleId` уходят
в поля журнала.

**Сбой записи не бросает**: он уходит строкой в лог PHP
(`shef.problems: запись логгера <канал> не прошла: …`).

## Какой уровень

Правила выбора — [../3_loglevel.md](../3_loglevel.md). Журнал событий знает
пять значений важности: `Info` и `Notice` → `INFO`, `Error` и выше → `ERROR`.

## Где читать проблемы

| где | как |
|---|---|
| Журнал событий | административная часть, фильтр по типу `SH_PROBLEMS_*`; типы в фильтр добавляет обработчик `main:OnEventLogGetAuditTypes` |
| Файлы логов | **Настройки → Учёт проблем → Логи**, страница `/bitrix/admin/shef_problems_logs.php` (только администратору) |
| На диске | `\Shef\Problems\Main\Constants::getLogDir()`: по умолчанию `<родитель корня сайта>/sh_log`; свой каталог — ключ `shef.problems` → `logDir` (абсолютный путь) в `/bitrix/.settings_extra.php`, см. [../security.md](../security.md) |

Класс `\Shef\Problems\Main\LogFiles` (список файлов, проверка имени, хвост
файла) обслуживает страницу логов и контрактом для соседей не является — см.
[api.md](api.md).

## Как проверить

```php
\Bitrix\Main\Loader::includeModule('shef.problems');
\Shef\Problems\Factory\SystemLoggerFactory::build(\Monolog\Level::Info)->warning('проверка');
echo \Shef\Problems\Main\Constants::getLogFullPath('sh_problems_problem');
```

Запись видна в журнале событий с типом `SH_PROBLEMS_PROBLEM` и в файле по
напечатанному пути. Тесты модуля — `./build.sh --check`.
