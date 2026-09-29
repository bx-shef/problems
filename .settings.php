<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля
 *
 * * requireModules -> обязательные модули
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses -> авто подгрузка классов
 * * registerNamespace -> авто подгрузка Namespace
 * * options -> опции устанавливаемые через окружение
 * * installEvents -> события для установки
 * * installDir -> пути установки файлов
 * * services -> сервисы для \Bitrix\Main\DI\ServiceLocator
 * * controllers -> контроллеры для ajax
 *
 * Классы самого модуля (Shef\Problems\...) в registerNamespace не нужны:
 * ядро отображает их в lib/ по соглашению. Там только чужие — Monolog.
 */

use Monolog\Level as MonologLevel;
use Bitrix\Main\Loader;
use Shef\Problems\Factory\SystemLoggerFactory;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Handler;
use Shef\Problems\Integration\Monolog\Processor;
use Shef\Problems\Main\Constants;

// region Monolog: Composer проекта либо своя копия ////
/**
 * Monolog приезжает двумя путями, и оба оставлены сознательно:
 *
 * * через Composer — пакет bxshef/problems требует monolog/monolog, и тот
 *   ложится в vendor проекта;
 * * своей копией в vendor/ модуля — для установки архивом, без Composer.
 *
 * Какой из двух подключать, решает ShProjectContext из shef.options: если в
 * vendor проекта Monolog есть, свою копию не регистрируем, классы даст
 * автозагрузчик Composer. Если нет — регистрируем свою.
 *
 * Путь модуля считается от корня сайта, потому что autoload.php приклеивает
 * к нему DOCUMENT_ROOT: модуль может стоять и в /bitrix/modules, и в
 * /local/modules. Каталог берётся у Loader::getLocal(), а не у __DIR__:
 * __DIR__ раскрывает симлинки, и модуль в /local-ссылке (схема разработки,
 * ext_www многосайтовой BitrixVM) оказался бы «вне корня» — с путём по
 * умолчанию в /bitrix/modules, где его нет.
 *
 * Без shef.options (не поставлен, сломан) или при ошибке разбора
 * composer.json проекта — своя копия: модулю без логгера хуже, чем
 * логгеру из собственного vendor.
 */
$shProblemsNamespaces = (static function(): array
{
	$monologPath = '/monolog/monolog/src/Monolog';

	$documentRoot = rtrim(str_replace('\\', '/', (string)Loader::getDocumentRoot()), '/');
	$moduleDir = Loader::getLocal('modules/shef.problems');
	$moduleDir = str_replace('\\', '/', is_string($moduleDir) ? $moduleDir : __DIR__);
	$modulePath = ($documentRoot !== '' && str_starts_with($moduleDir, $documentRoot.'/'))
		? mb_substr($moduleDir, mb_strlen($documentRoot))
		: '/bitrix/modules/shef.problems';

	$own = [
		'Monolog' => $modulePath.'/vendor'.$monologPath,
	];

	$contextFile = Loader::getLocal('modules/shef.options/project-context.php');
	if(!is_string($contextFile) || !is_file($contextFile))
	{
		return $own;
	}

	try
	{
		require_once $contextFile;

		return (new \ShProjectContext($modulePath))
			->addNamespace(new \ShProjectNamespaceComposer('Monolog', $monologPath))
			->getNamespaceList();
	}
	catch(\Throwable $throwable)
	{
		return $own;
	}
})();
// endregion ////

return [
	'requireModules' => [
		'value' => [
			'shef.options',
		],
		'readonly' => true,
	],
	'requirePhpExt' => [
		'value' => [],
		'readonly' => true,
	],
	'registerAutoLoadClasses' => [
		'value' => [],
		'readonly' => true,
	],
	'registerNamespace' => [
		'value' => $shProblemsNamespaces,
		'readonly' => true,
	],
	'options' => [
		'value' => [],
		'readonly' => true,
	],
	'installEvents' => [
		'value' => [
			[
				'isCompatible' => true,
				'from' => [
					'module' => 'main',
					'event' => 'OnPageStart'
				],
				'to' => [
					'module' => 'shef.problems',
					'class' => '\Shef\Problems\Integration\Main\Events',
					'function' => 'onPageStart',
					'sort' => 5
				]
			],
			[
				'isCompatible' => true,
				'from' => [
					'module' => 'main',
					'event' => 'OnEventLogGetAuditTypes'
				],
				'to' => [
					'module' => 'shef.problems',
					'class' => '\Shef\Problems\Integration\Main\Events',
					'function' => 'onEventLogGetAuditTypes'
				]
			],
		],
		'readonly' => true,
	],
	'installDir' => [
		'value' => [
			[
				'type' => 'js',
				'from' => '/install/js',
				'to' => '/bitrix/js',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
		],
		'readonly' => true,
	],
	'services' => [
		'value' => [
			'shef.problems.pr.debug' => [
				'handlerType' => ['print'],
				'constructor' => static function ()
				{
					return (new Logger('pr'))
						->pushHandler(
							(new Handler\PrHandler(
								isShowForAll: false,
								level: MonologLevel::Debug
							))
							->pushProcessor(new Processor\TraceProcessor())
							->setFormatter(Constants::getDefaultFormatter())
						)
					;
				}
			],
			'shef.problems.prHtml.debug' => [
				'handlerType' => ['print'],
				'constructor' => static function ()
				{
					return (new Logger('prHtml'))
						->pushHandler(
							(new Handler\PrHtmlHandler(
								isShowForAll: false,
								level: MonologLevel::Debug
							))
							->pushProcessor(new Processor\TraceProcessor())
							->setFormatter(Constants::getDefaultFormatter())
						)
					;
				}
			],
			'shef.problems.log.debug' => [
				'handlerType' => ['file'],
				'constructor' => static function ()
				{
					return (new Logger('log'))
						->pushHandler(
							// С потолком размера: отладочный лог на портале рос без
							// предела и забивал диск. Log1Handler не выход — он
							// стирал бы файл на каждом запросе, и Log стал бы Log1.
							(new Handler\CappedStreamHandler(
								filename: Constants::getLogFullPath(mb_strtolower(Shef\Problems\Logger::Log->name)),
								level: MonologLevel::Debug
							))
							->pushProcessor(new Processor\TraceProcessor(false))
							->setFormatter(Constants::getDefaultFormatter())
						)
					;
				}
			],
			'shef.problems.log1.debug' => [
				'handlerType' => ['file'],
				'constructor' => static function ()
				{
					return (new Logger('log1'))
						->pushHandler(
							(new Handler\Log1Handler(
								filename: Constants::getLogFullPath(mb_strtolower(Shef\Problems\Logger::Log1->name)),
								level: MonologLevel::Debug
							))
							->pushProcessor(new Processor\TraceProcessor(false))
							->setFormatter(Constants::getDefaultFormatter())
						)
					;
				}
			],
			'shef.problems.deprecations.alert' => [
				'handlerType' => ['file'],
				'constructor' => static function ()
				{
					return (new Logger('deprecations'))
						->pushHandler(
							(new Handler\Log1Handler(
								filename: Constants::getLogFullPath(mb_strtolower(Shef\Problems\Logger::Deprecations->name)),
								level: MonologLevel::Alert
							))
							->setFormatter(Constants::getDefaultFormatter())
						)
					;
				}
			],
			'shef.problems.factory.system.logger' => [
				'handlerType' => [
					'file',
					'bitrix.event.log'
				],
				'className' => SystemLoggerFactory::class,
			],
		],
		'readonly' => true,
	],
	'controllers' => [
		'value' => [
			'namespaces' => [],
		],
		'readonly' => true,
	]
];
