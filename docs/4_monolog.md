# [`\Shef\Problems\Integration\Monolog`] Monolog

## Почитать

* [Monolog](https://github.com/Seldaek/monolog)
* [Логирование в распределенном php-приложении](https://habr.com/ru/post/456676/)

## Откуда берётся Monolog

Из Composer проекта, если он там есть, иначе — из своей копии в
`vendor/monolog/monolog` модуля. Решает `.settings.php` модуля через
`ShProjectContext` из `shef.options`: если в vendor проекта Monolog лежит, своя
копия не регистрируется. Без `shef.options` или при ошибке разбора
`composer.json` проекта — своя копия.

Версия своей копии обязана подходить под ограничение из `composer.json` модуля
и не давать deprecation на поддерживаемых версиях PHP — сторожит
`tests/vendor_test.php`.

## Предустановленные логгеры

Сервисы `\Bitrix\Main\DI\ServiceLocator`, ключ `services` в `.settings.php`.
Каждому соответствует случай enum `\Shef\Problems\Logger`. Файлы — в каталоге
логов `\Shef\Problems\Main\Constants::getLogDir()`, вне корня сайта: при корне
`/home/bitrix/www` это `/home/bitrix/sh_log`, см. [security.md](security.md).

| сервис | enum | уровень | куда |
|---|---|---|---|
| `shef.problems.pr.debug` | `Pr` | Debug | на экран, без оформления; только администратору |
| `shef.problems.prHtml.debug` | `PrHtml` | Debug | на экран, с цветом по уровню; только администратору |
| `shef.problems.log.debug` | `Log` | Debug | `log.log` |
| `shef.problems.log1.debug` | `Log1` | Debug | `log1.log`, первая запись за запрос стирает файл |
| `shef.problems.deprecations.alert` | `Deprecations` | Alert | `deprecations.log`, тоже стирается первой записью |
| `shef.problems.factory.system.logger` | `Problems` | задаёт вызывающий | фабрика: файл по типу проблемы + журнал событий |

Через enum:

```php
\Bitrix\Main\Loader::includeModule('shef.problems');

\Shef\Problems\Logger::PrHtml->getLogger()->debug('что пришло', ['fields' => $fields]);
```

Через сервис напрямую:

```php
$logger = \Bitrix\Main\DI\ServiceLocator::getInstance()->get('shef.problems.log.debug');
$logger->info('Импорт начат');
```

`Logger::Problems->getLogger()` бросает `LogicException`: это фабрика, а не
логгер, её строят через трейт — ниже.

## Проблемы: фабрика и трейт

Проблема — запись, которую надо не только сохранить, но и найти потом в
журнале событий по типу и понять, кому она адресована. Фабрика
`\Shef\Problems\Factory\SystemLoggerFactory::build()` строит логгер, который
пишет сразу:

* в файл `<тип>.log` в каталоге логов;
* в журнал событий Битрикса с этим типом.

В каждую запись добавляются модуль, класс и ответственный.

Обычно фабрику напрямую не зовут, а подключают трейт
`\Shef\Problems\Factory\Trait\LoggerProblems`:

```php
\Bitrix\Main\Loader::includeModule('shef.problems');

final class OrdersExchange
{
	use \Shef\Problems\Factory\Trait\LoggerProblems;

	public function __construct()
	{
		$this->initLogger();
	}

	public static function getClassName(): string
	{
		return static::class;
	}

	public static function getModuleId(): string
	{
		return 'acme.exchange';
	}

	// Необязательное — умолчания: Info, SH_PROBLEMS_PROBLEM, «сотрудник по умолчанию».
	protected static function getLogLevel(): \Monolog\Level
	{
		return \Monolog\Level::Error;
	}

	protected static function getAuditType(): string
	{
		return \Shef\Problems\Main\Constants::AuditTypeSync;
	}

	public static function getAssignedId(): int
	{
		return \Shef\Problems\Main\Constants::getSyncUserId();
	}

	public function run(): void
	{
		$this->logger->critical('1С не ответила за 30 секунд', ['itemId' => 1024]);
	}
}
```

Трейт `\Shef\Problems\Factory\Trait\DebuggerProblems` — то же для отладки: даёт
`$this->debugger` на сервисе `shef.problems.prHtml.debug`.

| что | где |
|---|---|
| `\Shef\Problems\Factory\SystemLoggerFactory::build` | фабрика проблем |
| `\Shef\Problems\Factory\Trait\LoggerProblems::initLogger` | `$this->logger` на фабрике |
| `\Shef\Problems\Factory\Trait\LoggerProblems::configureLogger` | подменить логгер снаружи — например, в тесте |
| `\Shef\Problems\Factory\Trait\DebuggerProblems::initDebugger` | `$this->debugger` для отладки |
| `\Shef\Problems\Main\Constants::getDefUserId` | ответственный по умолчанию, из настроек |

Запускаемый пример — [examples/problems.php](../examples/problems.php).

## Свои логгеры через `\Bitrix\Main\Diag\Logger::create`

Ядро умеет создавать логгеры по имени из ключа `loggers` в
`/bitrix/.settings.php` или `/bitrix/.settings_extra.php`. Логгеры модуля туда
встают так:

```php
return [
	'loggers' => [
		'value' => [
			'shef.problems.prHtml' => [
				'constructor' => static function () {
					if(!\Bitrix\Main\Loader::includeModule('shef.problems'))
					{
						return null;
					}

					return \Shef\Problems\Logger::PrHtml->getLogger();
				},
			],
		],
		'readonly' => true,
	],
];
```

```php
\Bitrix\Main\Diag\Logger::create('shef.problems.prHtml')?->debug('сообщение', ['контекст']);
```

Собственный логгер с любыми обработчиками Monolog — например, файл плюс
Telegram для важного:

```php
'test' => [
	'constructor' => static function () {
		if(!\Bitrix\Main\Loader::includeModule('shef.problems'))
		{
			return null;
		}

		return (new \Shef\Problems\Integration\Monolog\Logger('test'))
			->pushHandler(new \Monolog\Handler\StreamHandler(
				stream: \Shef\Problems\Main\Constants::getLogFullPath('test'),
				level: \Monolog\Level::Debug
			))
			->pushHandler(new \Monolog\Handler\TelegramBotHandler(
				apiKey: '<ключ бота>',
				channel: '<id канала>',
				level: \Monolog\Level::Info
			));
	},
],
```

Ключ бота — секрет: держите его в `.settings_extra.php`, который не уезжает в
репозиторий проекта.

## Обработчики модуля

Все обработчики [Monolog](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#handlers)
плюс свои:

| класс | что делает |
|---|---|
| Handler\BitrixCEventLogHandler | запись в журнал событий; уровень сопоставляется с важностью журнала, см. [уровни](3_loglevel.md) |
| Handler\PrHandler | вывод на экран; по умолчанию только администратору; всё экранируется |
| Handler\PrHtmlHandler | то же с оформлением; стили — расширение `shef-problems.monolog-pr-html` |
| Handler\Log1Handler | файл, который первая запись за жизнь обработчика стирает |
| Processor\TraceProcessor | трассировка в `extra.trace`: откуда позвали логгер или, для исключения, его трассировка |
| Formatter\BitrixCEventLogFormatter | запись → описание для журнала; `itemId` и `moduleId` из контекста — в поля журнала |

**Вывод на экран экранируется.** В сообщение и контекст попадает что угодно, в
том числе ввод посетителя, а смотрит на вывод администратор. До 2.0.0 это шло в
страницу как есть.

**Трассировка начинается с места вызова**, где бы ни висел `TraceProcessor` —
на обработчике или на логгере: кадры самого Monolog и слоя интеграции
отрезаются по файлам, а не по счёту.

## Логгер `\Shef\Problems\Integration\Monolog\Logger`

Наследник `\Monolog\Logger`. Первым аргументом принимает не только строку:

| что передали | сообщение | контекст |
|---|---|---|
| `\Throwable` | текст исключения | само исключение под `throwable` |
| `\Bitrix\Main\Error` | текст и код | ошибка под `BitrixError` |
| `\Bitrix\Main\Result` | `[Result::Error: N] первая ошибка` либо `[Result::Success]` | ошибки и данные под `BitrixResult` |
| `\Bitrix\Main\Type\Contract\Arrayable` | `Arrayable` | `toArray()` под `_message` |
| массив | `Array` | массив под `_message` |
| `\Bitrix\Main\Type\Contract\Jsonable` | `Jsonable` | `toJson()` под `_message` |
| `\JsonSerializable` | `JsonSerializable` | `jsonSerialize()` под `_message` |
| строка, `\Stringable` | как есть | как передали |

Остальное — `InvalidArgumentException`: лучше увидеть ошибку сразу, чем
потерять запись молча. Превращения делают стратегии
`\Shef\Problems\Integration\Monolog\Strategy\LoggerConverter\IStrategy`, по одной
на тип. Запускаемый пример — [examples/logger.php](../examples/logger.php).

[← Уровни логирования](3_loglevel.md) | [↑ Содержание](../README.md) | [Ротация логов →](5_logrotate.md)
