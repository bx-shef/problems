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
 * Плюс пути к логам: из них собирается и запись, и ссылка в меню.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

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

Check::group('пути к логам');

\Bitrix\Main\Application::$documentRoot = '/var/www/portal';

Check::same('абсолютный', Constants::getLogFullPath('log'), '/var/www/portal/local/sh_log/log.log');
Check::same('от корня сайта', Constants::getLogFullPath('log', false), '/local/sh_log/log.log');

Check::finish();
