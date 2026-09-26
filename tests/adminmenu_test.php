<?php declare(strict_types=1);

/**
 * Меню «Учёт проблем» в административной части.
 *
 * Заменяет пункты, которые до 2.0.0 вешались на верхнюю панель через
 * shef.uiclear. Что держит тест:
 *
 * * admin/menu.php отдаёт меню только администратору: в логах трассировки,
 *   пути и данные запросов;
 * * логи открываются страницей модуля /bitrix/admin/shef_problems_logs.php:
 *   каталог логов вне корня сайта, ни прямая ссылка, ни файловый менеджер
 *   до него не дотянутся;
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

Check::group('логи — через страницу модуля');

$menu = AdminMenu::build('ru');
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

$outside = array_values(array_filter(
	$urls,
	static fn(string $url): bool => str_contains($url, 'fileman') || str_contains($url, 'sh_log')
));
Check::same('ни файлового менеджера, ни путей к каталогу логов в ссылках', $outside, []);

$logs = $menu['items'][0]['items'];
$expectedFiles = array_merge(
	['log1', 'log'],
	array_map('mb_strtolower', Constants::getAuditTypeList()),
	['deprecations', 'exceptions', 'mailer']
);
$expectedUrls = array_map(
	static fn(string $name): string => AdminMenu::getUrlLogFile($name.'.log', 'ru'),
	$expectedFiles
);

Check::same(
	'каждый файловый логгер и каждый тип события — пункт меню',
	array_slice(array_column($logs, 'url'), 0, count($expectedUrls)),
	$expectedUrls
);
Check::same(
	'адрес просмотра файла',
	AdminMenu::getUrlLogFile('log.log', 'ru'),
	'/bitrix/admin/shef_problems_logs.php?lang=ru&file=log.log'
);
Check::same('последний пункт — все логи', end($logs)['url'], '/bitrix/admin/shef_problems_logs.php?lang=ru');

// Имя из меню страница обязана принять: иначе пункт вёл бы в «файла нет».
$rejected = array_values(array_filter(
	$expectedFiles,
	static fn(string $name): bool => !\Shef\Problems\Main\LogFiles::isValidName($name.'.log')
));
Check::same('страница логов принимает каждое имя из меню', $rejected, []);

Check::group('страница логов раскладывается установщиком');

$settings = require $root.'/.settings.php';
$adminMap = array_values(array_filter(
	$settings['installDir']['value'],
	static fn(array $map): bool => $map['to'] === '/bitrix/admin'
))[0] ?? [];

Check::same(
	'заглушка лежит в install/admin под тем именем, что в меню',
	is_file($root.($adminMap['from'] ?? '').'/'.basename(AdminMenu::LOGS_PAGE)),
	true
);
Check::same(
	'удаление снимает ровно этот файл, а не /bitrix/admin',
	$adminMap['customPathUnInstall'] ?? null,
	[AdminMenu::LOGS_PAGE]
);

Check::group('страница логов на портале, обновлённом заменой файлов');

// Установщик на таком портале не запускался — страницы нет. Меню её кладёт.
$portal = sys_get_temp_dir().'/shef-problems-menu-'.getmypid();
mkdir($portal.'/www/bitrix/admin', 0777, true);
$target = $portal.'/www'.AdminMenu::LOGS_PAGE;

Check::same('страницы нет — кладёт', AdminMenu::ensureLogsPage($portal.'/www', $root), true);
Check::same('это заглушка модуля', (string)file_get_contents($target), (string)file_get_contents($root.'/install/admin/shef_problems_logs.php'));

file_put_contents($target, 'своя версия проекта');
Check::same('есть — не трогает', [AdminMenu::ensureLogsPage($portal.'/www', $root), (string)file_get_contents($target)], [true, 'своя версия проекта']);

Check::same('нет /bitrix/admin — не создаёт его', AdminMenu::ensureLogsPage($portal.'/нет', $root), false);

\Bitrix\Main\Application::$documentRoot = $portal.'/www';
unlink($target);
$includeMenu(new CUser(true));
Check::same('admin/menu.php кладёт страницу сам', is_file($target), true);

\Bitrix\Main\IO\Directory::deleteDirectory($portal);
\Bitrix\Main\Application::$documentRoot = '';

Check::group('журнал событий');

$eventLog = $menu['items'][1]['items'];
Check::same('пункт на каждый тип события', count($eventLog), count(Constants::getAuditTypeList()));
Check::same(
	'фильтр по типу',
	AdminMenu::getUrlEventLog(Constants::AuditTypeSale, 'ru'),
	'/bitrix/admin/event_log.php?lang=ru&set_filter=Y&adm_filter_applied=0&find_type=audit_type_id&find_audit_type%5B0%5D=SH_PROBLEMS_SALE'
);

$withPerfmon = AdminMenu::build('ru', true)['items'][1]['items'];
Check::same('с perfmon — плюс ошибки платёжных систем', count($withPerfmon), count($eventLog) + 1);

ModuleManager::$installed = ['perfmon'];
$menu = $includeMenu(new CUser(true));
Check::same('admin/menu.php сам видит perfmon', count($menu['items'][1]['items']), count($eventLog) + 1);

Check::group('настройки');

Check::same('ссылка на страницу настроек', end($menu['items'])['url'], '/bitrix/admin/settings.php?lang=ru&mid=shef.problems');

Check::finish();
