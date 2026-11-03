# Публичный API shef.problems

Модуль `shef.problems`, namespace `\Shef\Problems`. Подключение —
`\Bitrix\Main\Loader::includeModule('shef.problems')`. REST и контроллеров нет.

## Можно использовать

### `\Shef\Problems\Factory\SystemLoggerFactory`

| метод | что |
|---|---|
| `build(\Monolog\Level $logLevel, string $auditType = 'SH_PROBLEMS_PROBLEM', string $moduleId = 'empty', string $className = 'empty', ?int $assigned = null): Integration\Monolog\Logger` | логгер проблем: файл + журнал событий; см. [logging.md](logging.md) |

### Трейты `\Shef\Problems\Factory\Trait`

| трейт | методы |
|---|---|
| `LoggerProblems` | abstract `getClassName(): string`, `getModuleId(): string`; переопределяемые `getAssignedId(): int`, `getLogLevel(): \Monolog\Level`, `getAuditType(): string`; `initLogger(): void` → `$this->logger`; `configureLogger(LoggerInterface): static` |
| `DebuggerProblems` | `initDebugger(): void` → `$this->debugger` (сервис `shef.problems.prHtml.debug`); `configureDebugger(LoggerInterface): static` |

### enum `\Shef\Problems\Logger`

Случаи `Pr`, `PrHtml`, `Log1`, `Log`, `Problems`, `Deprecations`;
методы `getLogger(): LoggerInterface`, `getServiceName(): string`.
`Logger::Problems->getLogger()` бросает `LogicException` — это фабрика, её
строят через трейт. Сервисы и уровни — [../4_monolog.md](../4_monolog.md).

### `\Shef\Problems\Main\Constants`

| имя | значение |
|---|---|
| `MODULE_ID` | `'shef.problems'` |
| `AuditTypeProblem`, `AuditTypeSync`, `AuditTypeProduct`, `AuditTypeSale` | `SH_PROBLEMS_PROBLEM`, `SH_PROBLEMS_SYNC`, `SH_PROBLEMS_PRODUCT`, `SH_PROBLEMS_SALE` |
| `getAuditTypeList(): array`, `getDefAuditType(): string` | список типов, тип по умолчанию |
| `getLogDir(): string`, `getLogFullPath(string $name): string` | каталог логов (без `/` на конце) и `<каталог>/<имя>.log` |
| `getDefUserId()`, `getAdminId()`, `getDirectorId()`, `getSyncUserId()`, `getProductsUserId()`, `getSaleUserId()`: `int` | ответственные из настроек модуля (опции `DEF_*`), умолчание `DEFAULT_USER_ID` = 1 |
| `getIdOption(string $code): int` | такая опция по коду, например `DEF_adminid` |
| `getGroupIdTask()`, `getGroupIdIntegrateB24()` | отдают умолчание: задать значение в интерфейсе негде (CLAUDE.md, «Известные шероховатости») |

### `\Shef\Problems\Integration\Monolog\Logger`

Наследник `\Monolog\Logger`; первым аргументом принимает не только строку
(таблица — [../4_monolog.md](../4_monolog.md)). Обработчики
`Integration\Monolog\Handler\*`, `Processor\TraceProcessor`,
`Formatter\BitrixCEventLogFormatter` — для своих логгеров.

### `\Shef\Problems\Throwable\Manager`

| метод | что |
|---|---|
| `buildError(\Throwable $throwable, bool $isUseTrace = true, int\|string $code = 0, mixed $customData = null): \Bitrix\Main\Error` | ошибка ядра; трассировка без аргументов вызовов и без `DOCUMENT_ROOT` |
| `traceToString(array $trace): string` | трассировка строкой без аргументов вызовов |

### `\Shef\Problems\Main\Utils`

`getAllParents(object $instance): string`;
`workDateAdd(\Bitrix\Main\Type\Date $date, string $interval): \Bitrix\Main\Type\Date`
(рабочий календарь через bizproc, без него — обычное `add`);
`getUserBoss(int $userId, bool $skipAbsent = true, bool $clearCache = false, int $topLevel = 5): array`.

### Функции `def-functions.php`

`_pr($o, bool $show = false)`, `_log(array $value = [], string $fileName = 'log-custom')`,
`_log1(array $value = [], string $fileName = 'log1-custom')` — см.
[../2_deffunctions.md](../2_deffunctions.md). Для отладки, не для боевых записей.

## События

| событие | кто шлёт | что |
|---|---|---|
| `main:OnPageStart` → `Integration\Main\Events::onPageStart` | ядро | стили `PrHtml` в административной части |
| `main:OnEventLogGetAuditTypes` → `Integration\Main\Events::onEventLogGetAuditTypes` | ядро | типы `SH_PROBLEMS_*` в фильтре журнала |
| `shef.problems:onModuleUnInstall` | модуль, в `UnInstallEvents()` | без параметров; только если есть обработчики |

Подробно — [../1_events.md](../1_events.md).

## Не использовать (внутреннее)

`Main\AdminPage`, `Main\LogFiles`, `Integration\Main\AdminMenu`,
`Integration\Shef\UiClear\Events` (заглушка для порталов, обновлённых с 1.x),
`Integration\Bizproc\EmptyActivity`, стратегии
`Integration\Monolog\Strategy\LoggerConverter\*` — внутренности страницы логов,
меню и логгера; их сигнатуры меняются без мажорной версии.
