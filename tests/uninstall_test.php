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
 * * Файлы ставятся из того каталога, где модуль стоит на самом деле, а не из
 *   зашитого /bitrix/modules: модуль из /local/modules иначе не ставил ничего.
 * * Удаление: своя заглушка страницы логов уходит, чужой файл на её месте,
 *   сам /bitrix/admin и чужие расширения остаются; логи не трогаются.
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

	/** @var list<array{0: string, 1: string}> что установщик копировал */
	public static array $copied = [];

	public static function reset(): void
	{
		static::$unregistered = [];
		static::$cacheCleaned = 0;
		static::$copied = [];
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

/** Копирование каталогов ядра: запоминаем откуда и куда. */
function CopyDirFiles(string $from, string $to, bool $rewrite = true, bool $recursive = false): bool
{
	CoreCalls::$copied[] = [$from, $to];
	return true;
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

// Класс — ровно как записан, без ltrim: ядро снимает регистрацию по точному
// совпадению TO_CLASS, а 1.1.7 регистрировал его с ведущим «\».
$unregistered = array_map(
	static fn(array $call): string => $call[0].':'.$call[1].' -> '.$call[3].'::'.$call[4],
	EventManager::$unregistered
);

Check::same('снято ровно три обработчика', count($unregistered), 3);
Check::same(
	'свой OnPageStart',
	in_array('main:OnPageStart -> \\Shef\\Problems\\Integration\\Main\\Events::onPageStart', $unregistered, true),
	true
);
Check::same(
	'обработчик shef.uiclear из 1.x',
	in_array('shef.uiclear:onBitrixMenuExtInitTopPanelUserMenu -> \\Shef\\Problems\\Integration\\Shef\\UiClear\\Events::onBitrixMenuExtInitTopPanelUserMenu', $unregistered, true),
	true
);

Check::group('установка файлов: из того каталога, где стоит модуль');

// Портал в песочнице: корень сайта — www, каталог логов — рядом.
$portal = sys_get_temp_dir().'/shef-problems-uninstall-'.getmypid();
\Bitrix\Main\Application::$documentRoot = $portal.'/www';

$touch = static function(string $path, string $content = 'x'): void
{
	if(!is_dir(dirname($path)))
	{
		mkdir(dirname($path), 0777, true);
	}
	file_put_contents($path, $content);
};

$touch($portal.'/www/bitrix/admin/settings.php');
$touch($portal.'/www/bitrix/js/main/core/core.js');
$touch($portal.'/www/bitrix/images/shef.problems/docs/scr1.png');
$touch($portal.'/sh_log/sh_problems_sync.log');

$module = $given();
Check::same('InstallFiles отработал', $module->InstallFiles(), true);

// Каталог модуля берётся у самого установщика, а не зашитый /bitrix/modules:
// модуль здесь лежит вне корня сайта-песочницы, и файлы всё равно нашлись.
Check::same('стили разложены из каталога модуля', CoreCalls::$copied, [[$root.'/install/js', $portal.'/www/bitrix/js']]);

$logsPage = $portal.'/www/bitrix/admin/'.\Shef\Problems\Main\AdminPage::FILE;
Check::same(
	'заглушка страницы логов ведёт в этот модуль',
	(string)@file_get_contents($logsPage),
	\Shef\Problems\Main\AdminPage::getContent($portal.'/www', $root)
);

Check::group('удаление файлов: своё уносим, чужое и логи — нет');

$touch($portal.'/www/bitrix/js/shef-problems/monolog-pr-html/style.css');

$module = $given();
Check::same('UnInstallFiles отработал', $module->UnInstallFiles(), true);

Check::same('своя заглушка страницы логов убрана', is_file($logsPage), false);
Check::same('страницы ядра в /bitrix/admin на месте', is_file($portal.'/www/bitrix/admin/settings.php'), true);
Check::same('стили модуля убраны', is_dir($portal.'/www/bitrix/js/shef-problems'), false);
Check::same('чужие расширения на месте', is_file($portal.'/www/bitrix/js/main/core/core.js'), true);
Check::same('скриншоты от 1.x убраны', is_dir($portal.'/www/bitrix/images/shef.problems'), false);
Check::same('логи — данные проекта, остались', is_file($portal.'/sh_log/sh_problems_sync.log'), true);

// Проект положил на место заглушки свой файл — удаление его не трогает.
$touch($logsPage, '<?php // своя страница проекта');
$module = $given();
$module->UnInstallFiles();
Check::same('чужой файл на месте заглушки не удалён', (string)file_get_contents($logsPage), '<?php // своя страница проекта');

\Bitrix\Main\IO\Directory::deleteDirectory($portal);

Check::group('заглушка для 1.x отвечает, а не падает');

require_once $root.'/tests/stub/autoload.php';

$response = \Shef\Problems\Integration\Shef\UiClear\Events::onBitrixMenuExtInitTopPanelUserMenu(new \Bitrix\Main\Event());
Check::same('ответ — «нечего добавить»', $response->getType(), \Bitrix\Main\EventResult::UNDEFINED);

Check::finish();
