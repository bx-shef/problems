<?php declare(strict_types=1);

/**
 * Страница настроек: options_conf.php собирается против API shef.options 3.x.
 *
 * Что держит:
 *
 * * options_conf.php зовёт ShOptionsConfig так, как его понимает shef.options
 *   3.x. В 1.1.7 он передавал indexDoc — параметр ушёл в 3.0.0, и страница
 *   настроек падала «Unknown named parameter». Ни php -l, ни остальные тесты
 *   этого не видели;
 * * у каждой подписи есть перевод: setName() и setTitle() принимают строку,
 *   и пропущенный ключ языкового файла — это TypeError, то есть снова
 *   неоткрывающаяся страница;
 * * со страницы настроек есть ссылка на страницу логов и виден каталог логов:
 *   логи лежат вне корня сайта, прямой ссылки на них нет;
 * * каждая роль из Constants — опция вкладки «Сотрудники».
 *
 * API shef.options подменяет tests/stub/options.php, файлы модуля настоящие.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/stub/options.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;
use Shef\Problems\Integration\Main\AdminMenu;

define('LANGUAGE_ID', 'ru');
Application::$documentRoot = '/home/bitrix/www';
Loc::loadLangFile($root.'/lang/ru/options.php');

Check::group('options_conf.php собирается');

$tabs = require $root.'/options_conf.php';

Check::same('вернул список вкладок', is_array($tabs), true);
Check::same('вкладка «Сотрудники»', array_map(static fn(Options\Tab $tab): string => $tab->getCode(), $tabs), ['DEF']);
Check::same('у вкладки есть название', $tabs[0]->getName(), 'Сотрудники');

$options = [];
foreach($tabs[0]->getOptionList() as $option)
{
	$options[$option->getCode()] = $option;
}

Check::group('ссылка на логи');

$logs = $options['Logs'] ?? null;
Check::same('строка с логами есть', $logs instanceof Options\RowInfo, true);
Check::same(
	'ведёт на страницу логов',
	str_contains((string)$logs?->getDescription(), '[URL='.AdminMenu::getUrlLogList('ru').']'),
	true
);
Check::same('показывает каталог логов', str_contains((string)$logs?->getDescription(), '/home/bitrix/sh_log'), true);

Check::group('роли');

$roles = ['defuserid', 'adminid', 'dirid', 'syncuserid', 'productsuserid', 'saleuserid'];
$missing = array_values(array_filter(
	$roles,
	static fn(string $code): bool => !(($options[$code] ?? null) instanceof Options\Users)
));
Check::same('каждая роль — выбор пользователя', $missing, []);

$untitled = array_values(array_filter(
	$roles,
	static fn(string $code): bool => ($options[$code] ?? null)?->getTitle() === ''
));
Check::same('у каждой роли есть подпись', $untitled, []);

Check::finish();
