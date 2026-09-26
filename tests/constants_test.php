<?php declare(strict_types=1);

/**
 * Кому уходит проблема: ID сотрудников из настроек модуля.
 *
 * В 1.1.7 значение читалось как (int)Option::get(...). Это ровно та ловушка,
 * за которую в shef.options уже заплачено: intval('5 62') — 5, intval(true)
 * — 1, и опечатка в настройке молча отправляет проблему не тому человеку.
 * Теперь разбор строгий: целое больше нуля либо строка из одних цифр без
 * ведущего нуля, всё прочее — пользователь по умолчанию.
 *
 * Плюс каталог логов: он обязан лежать вне корня сайта, а путь проекта из
 * настроек принимается только абсолютный.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Config\Option;
use Shef\Problems\Main\Constants;

$with = static function(mixed $value): int
{
	Option::$values = [];

	if(null !== $value)
	{
		Option::set(Constants::MODULE_ID, 'DEF_adminid', $value);
	}

	return Constants::getAdminId();
};

Check::group('корректные значения');

Check::same('настройка не задана — по умолчанию', $with(null), Constants::DEFAULT_USER_ID);
Check::same('строка из цифр', $with('562'), 562);
Check::same('целое', $with(7), 7);

Check::group('мусор — по умолчанию, а не «что-нибудь»');

Check::same('пробел внутри: не 5', $with('5 62'), Constants::DEFAULT_USER_ID);
Check::same('ведущий ноль', $with('07'), Constants::DEFAULT_USER_ID);
Check::same('ноль', $with('0'), Constants::DEFAULT_USER_ID);
Check::same('отрицательное', $with(-3), Constants::DEFAULT_USER_ID);
Check::same('пустая строка', $with(''), Constants::DEFAULT_USER_ID);
Check::same('true: не 1 «случайно»', $with(true), Constants::DEFAULT_USER_ID);
Check::same('массив', $with([562]), Constants::DEFAULT_USER_ID);
Check::same('сериализованный список', $with(serialize(['562'])), Constants::DEFAULT_USER_ID);

Check::group('все роли читаются одинаково');

Option::$values = [];
$roles = [
	'DEF_defuserid' => 'getDefUserId',
	'DEF_adminid' => 'getAdminId',
	'DEF_dirid' => 'getDirectorId',
	'DEF_syncuserid' => 'getSyncUserId',
	'DEF_productsuserid' => 'getProductsUserId',
	'DEF_saleuserid' => 'getSaleUserId',
];

$id = 100;
$wrong = [];
foreach($roles as $code => $method)
{
	$id++;
	Option::set(Constants::MODULE_ID, $code, (string)$id);

	if(Constants::$method() !== $id)
	{
		$wrong[] = $method;
	}
}

Check::same('каждый метод читает свою настройку', $wrong, []);

Check::group('умолчания настроек совпадают с кодом');

$shef_problems_default_option = [];
require $root.'/default_option.php';

$mismatch = [];
foreach(array_keys($roles) as $code)
{
	if(($shef_problems_default_option[$code] ?? null) !== (string)Constants::DEFAULT_USER_ID)
	{
		$mismatch[] = $code;
	}
}

Check::same('default_option.php знает все роли и даёт DEFAULT_USER_ID', $mismatch, []);

Check::group('каталог логов — вне корня сайта');

// Логи с трассировками и данными запросов не должны лежать там, откуда их
// отдаёт веб-сервер. До 2.0.0 это был /local/sh_log — под корнем.
Application::$documentRoot = '/home/bitrix/www';
Check::same('BitrixVM: рядом с www', Constants::getLogDir(), '/home/bitrix/sh_log');
Check::same('файл лога', Constants::getLogFullPath('log'), '/home/bitrix/sh_log/log.log');
Check::same('каталог не под корнем сайта', str_starts_with(Constants::getLogDir().'/', Application::getDocumentRoot().'/'), false);

Application::$documentRoot = '/home/bitrix/www/';
Check::same('слэш на конце корня не мешает', Constants::getLogDir(), '/home/bitrix/sh_log');

Application::$documentRoot = '/www';
Check::same('корень сайта в корне ФС — без двойного слэша', Constants::getLogDir(), '/sh_log');

Application::$documentRoot = '';
Check::same(
	'корня сайта нет (CLI) — временный каталог, а не /sh_log',
	Constants::getLogDir(),
	rtrim(sys_get_temp_dir(), '/').'/sh_log'
);

Check::group('каталог логов из настроек проекта');

Application::$documentRoot = '/home/bitrix/www';

Configuration::$values[Constants::SETTINGS_KEY] = [Constants::SETTINGS_LOG_DIR => '/var/log/portal/'];
Check::same('абсолютный путь проекта', Constants::getLogDir(), '/var/log/portal');

$ignored = [];
foreach(['logs', '../sh_log', '', '/', 42, null] as $value)
{
	Configuration::$values[Constants::SETTINGS_KEY] = [Constants::SETTINGS_LOG_DIR => $value];
	if(Constants::getLogDir() !== '/home/bitrix/sh_log')
	{
		$ignored[] = var_export($value, true);
	}
}
Check::same('не абсолютный путь — по умолчанию', $ignored, []);

Configuration::$values[Constants::SETTINGS_KEY] = 'не массив';
Check::same('ключ не массив — по умолчанию', Constants::getLogDir(), '/home/bitrix/sh_log');

Configuration::$values = [];

Check::finish();
