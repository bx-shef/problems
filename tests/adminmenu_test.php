<?php declare(strict_types=1);

/**
 * Меню «Учёт проблем» в административной части.
 *
 * Заменяет пункты, которые до 2.0.0 вешались на верхнюю панель через
 * shef.uiclear. Что держит тест:
 *
 * * admin/menu.php отдаёт меню только администратору: в логах трассировки,
 *   пути и данные запросов;
 * * логи открываются через просмотр файлов fileman, а не прямой ссылкой на
 *   /local/sh_log — прямая ссылка работает, только если каталог открыт всем;
 * * в меню есть каждый файловый логгер и каждый тип события;
 * * ссылка в perfmon — только если perfmon стоит.
 *
 * admin/menu.php подключается настоящий: ядро зовёт именно его.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\ModuleManager;
use Shef\Problems\Integration\Main\AdminMenu;
use Shef\Problems\Main\Constants;

\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';

// region Заглушки окружения admin/menu.php ////
define('B_PROLOG_INCLUDED', true);
define('LANGUAGE_ID', 'ru');

class CUser
{
	public function __construct(private readonly bool $isAdmin) {}

	public function IsAdmin(): bool
	{
		return $this->isAdmin;
	}
}

class CSite
{
	public static function GetDefSite(): string
	{
		return 's1';
	}
}

/** Подключить admin/menu.php так, как это делает ядро. */
$includeMenu = static function(?CUser $user) use ($root): mixed
{
	$GLOBALS['USER'] = $user;

	return include $root.'/admin/menu.php';
};
// endregion ////

Check::group('admin/menu.php: права');

Check::same('без пользователя — меню нет', $includeMenu(null), false);
Check::same('не администратору — меню нет', $includeMenu(new CUser(false)), false);

$menu = $includeMenu(new CUser(true));
Check::same('администратору — раздел', is_array($menu), true);
Check::same('раздел в «Настройках»', $menu['parent_menu'] ?? null, 'global_menu_settings');

Check::group('логи — через fileman');

$menu = AdminMenu::build('ru', 's1');
$urls = [];
$walk = static function(array $items) use (&$walk, &$urls): void
{
	foreach($items as $item)
	{
		if(isset($item['url']))
		{
			$urls[] = $item['url'];
		}

		$walk($item['items'] ?? []);
	}
};
$walk($menu['items']);

$direct = array_values(array_filter($urls, static fn(string $url): bool => str_starts_with($url, Constants::getLogPath())));
Check::same('прямых ссылок на файлы логов нет', $direct, []);

$logs = $menu['items'][0]['items'];
$expectedFiles = array_merge(
	['log1', 'log'],
	array_map('mb_strtolower', Constants::getAuditTypeList()),
	['deprecations', 'exceptions', 'mailer']
);
$expectedUrls = array_map(
	static fn(string $name): string => AdminMenu::getUrlLogFile($name, 'ru', 's1'),
	$expectedFiles
);

Check::same(
	'каждый файловый логгер и каждый тип события — пункт меню',
	array_slice(array_column($logs, 'url'), 0, count($expectedUrls)),
	$expectedUrls
);
Check::same(
	'адрес просмотра файла',
	AdminMenu::getUrlLogFile('log', 'ru', 's1'),
	'/bitrix/admin/fileman_file_view.php?lang=ru&site=s1&path=%2Flocal%2Fsh_log%2Flog.log'
);
Check::same('последний пункт — каталог логов', end($logs)['url'], AdminMenu::getUrlLogList('ru', 's1'));

Check::group('журнал событий');

$eventLog = $menu['items'][1]['items'];
Check::same('пункт на каждый тип события', count($eventLog), count(Constants::getAuditTypeList()));
Check::same(
	'фильтр по типу',
	AdminMenu::getUrlEventLog(Constants::AuditTypeSale, 'ru'),
	'/bitrix/admin/event_log.php?lang=ru&set_filter=Y&adm_filter_applied=0&find_type=audit_type_id&find_audit_type%5B0%5D=SH_PROBLEMS_SALE'
);

$withPerfmon = AdminMenu::build('ru', 's1', true)['items'][1]['items'];
Check::same('с perfmon — плюс ошибки платёжных систем', count($withPerfmon), count($eventLog) + 1);

ModuleManager::$installed = ['perfmon'];
$menu = $includeMenu(new CUser(true));
Check::same('admin/menu.php сам видит perfmon', count($menu['items'][1]['items']), count($eventLog) + 1);

Check::group('настройки');

Check::same('ссылка на страницу настроек', end($menu['items'])['url'], '/bitrix/admin/settings.php?lang=ru&mid=shef.problems');

Check::finish();
