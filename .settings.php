<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля
 *
 * * requireModules -> обязательные модулей
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses -> авто подгрузка классов
 * * registerNamespace -> авто подгрузка Namespace
 * * options -> опции устанавливаемые через окружение
 * * installEvents -> события для установки
 * * installDir -> пути установки файлов
 * * controllers -> контроллеры для ajax
 * * ui.entity-selector -> провайдер для диалога выбора сущностей
 * * intranet.customSection -> указывает провайдер страниц левого меню Если нужно использовать из другого модуля - то в installLeftMenu[] указываем moduleId
 * * installLeftMenu -> разделы и страницы в левом меню
 *
 * @memo installLeftMenu[].pages[].settingsRow не серилизовать.
 * @memo installLeftMenu[].code и installLeftMenu[].pages[].code писать без разделителей
 * @memo installLeftMenu[].pages[].settingsRow первый параметр компонет. Остальное смотреть в контроллере intranet.customSection
 *
 */

use Monolog\Level as MonologLevel;
use Monolog\Handler as MonologHandler;
use Bitrix\Main\Loader;
use Shef\Problems\Factory\SystemLoggerFactory;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Handler;
use Shef\Problems\Integration\Monolog\Processor;
use Shef\Problems\Main\Constants;

if(!Loader::includeModule('shef.options'))
{
	return [];
}

return [
	'requireModules' => [
		'value' => [
			'shef.options',
			'shef.uiclear',
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
		'value' => [
			'Shef\\Problems' => '/bitrix/modules/shef.problems/lib',
			'Monolog' => '/bitrix/modules/shef.problems/vendor/monolog/monolog/src/Monolog',
		],
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
			[
				'isCompatible' => false,
				'from' => [
					'module' => 'shef.uiclear',
					'event' => 'onBitrixMenuExtInitTopPanelUserMenu'
				],
				'to' => [
					'module' => 'shef.problems',
					'class' => '\Shef\Problems\Integration\Shef\UiClear\Events',
					'function' => 'onBitrixMenuExtInitTopPanelUserMenu'
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
			[
				'type' => 'images',
				'from' => '/install/images',
				'to' => '/bitrix/images',
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
							(new MonologHandler\StreamHandler(
								stream: Constants::getLogFullPath(mb_strtolower(Shef\Problems\Logger::Log->name)),
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
		'value' => [],
		'readonly' => true,
	]
];