<?php declare(strict_types=1);

/**
 * Удаление модуля: уносит своё, не трогает чужое, прибирает за 1.x.
 *
 * * Настройки уходят вместе с модулем (решение владельца в shef.options):
 *   иначе повторная установка молча поднимает прежние значения. Проверяются
 *   обе стороны — Option::delete() работает по модулю, и ошибка в
 *   идентификаторе унесла бы настройки соседа.
 * * savedata = Y оставляет настройки — уговор ядра.
 * * Регистрация обработчика shef.uiclear из 1.x снимается: замена файлов её
 *   не снимает, а в installEvents её больше нет.
 *
 * Ядро подменяется заглушками, установщик подключается настоящий.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;

// region Заглушка ядра ////
class CoreCalls
{
	/** @var list<string> */
	public static array $unregistered = [];

	public static int $cacheCleaned = 0;

	public static function reset(): void
	{
		static::$unregistered = [];
		static::$cacheCleaned = 0;
		EventManager::$unregistered = [];
	}
}

if(!class_exists('CModule'))
{
	class CModule
	{
	}
}

function IsModuleInstalled(string $moduleId): bool
{
	return false;
}

function UnRegisterModule(string $moduleId): void
{
	CoreCalls::$unregistered[] = $moduleId;
}

function RegisterModule(string $moduleId): void
{
}

$GLOBALS['APPLICATION'] = new class
{
	public function ThrowException(string $message): void {}
};

$GLOBALS['CACHE_MANAGER'] = new class
{
	public function CleanAll(): void
	{
		CoreCalls::$cacheCleaned++;
	}
};

// Установщик читает installEvents из настоящего .settings.php.
\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';
// endregion ////

require_once $root.'/install/index.php';

const NEIGHBOUR = 'shef.options';

$given = static function(): shef_problems
{
	CoreCalls::reset();
	Option::$values = [];

	Option::set('shef.problems', 'DEF_adminid', '7');
	Option::set('shef.problems', 'DEF_dirid', '8');
	Option::set(NEIGHBOUR, 'DEF_systemuserid', '9');

	return new shef_problems();
};

Check::group('удаление уносит настройки модуля');

$module = $given();
$module->UnInstallDB();

Check::same('идентификатор модуля тот самый', $module->MODULE_ID, 'shef.problems');
Check::same('настройка стёрта', Option::get('shef.problems', 'DEF_adminid', 'нет'), 'нет');
Check::same('вторая тоже', Option::get('shef.problems', 'DEF_dirid', 'нет'), 'нет');
Check::same('модуль снят с регистрации', CoreCalls::$unregistered, ['shef.problems']);
Check::same('кеш сброшен', CoreCalls::$cacheCleaned, 1);

Check::group('чужое не трогаем');

Check::same('настройка shef.options на месте', Option::get(NEIGHBOUR, 'DEF_systemuserid', 'нет'), '9');

Check::group('savedata');

$module = $given();
$module->UnInstallDB(['savedata' => 'Y']);
Check::same('savedata = Y оставляет настройки', Option::get('shef.problems', 'DEF_adminid', 'нет'), '7');

$module = $given();
$module->UnInstallDB(['savedata' => 'N']);
Check::same('savedata = N стирает', Option::get('shef.problems', 'DEF_adminid', 'нет'), 'нет');

Check::group('обработчики снимаются, включая оставшийся от 1.x');

$module = $given();
$module->UnInstallEvents();

$unregistered = array_map(
	static fn(array $call): string => $call[0].':'.$call[1].' -> '.ltrim($call[3], '\\').'::'.$call[4],
	EventManager::$unregistered
);

Check::same('снято ровно три обработчика', count($unregistered), 3);
Check::same(
	'свой OnPageStart',
	in_array('main:OnPageStart -> Shef\\Problems\\Integration\\Main\\Events::onPageStart', $unregistered, true),
	true
);
Check::same(
	'обработчик shef.uiclear из 1.x',
	in_array('shef.uiclear:onBitrixMenuExtInitTopPanelUserMenu -> Shef\\Problems\\Integration\\Shef\\UiClear\\Events::onBitrixMenuExtInitTopPanelUserMenu', $unregistered, true),
	true
);

Check::group('заглушка для 1.x отвечает, а не падает');

require_once $root.'/tests/stub/autoload.php';

$response = \Shef\Problems\Integration\Shef\UiClear\Events::onBitrixMenuExtInitTopPanelUserMenu(new \Bitrix\Main\Event());
Check::same('ответ — «нечего добавить»', $response->getType(), \Bitrix\Main\EventResult::UNDEFINED);

Check::finish();
