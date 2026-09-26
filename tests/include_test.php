<?php declare(strict_types=1);

/**
 * Подключение модуля даёт _log(), _log1(), _pr() — и именно свои.
 *
 * Те же три функции объявляет shef.options, и каждая закрыта
 * function_exists: побеждает тот, кто объявил первым. include.php подключает
 * def-functions.php ДО autoload.php — а autoload.php подключает shef.options.
 * Поменяй порядок, и функции всегда брались бы из shef.options и писали бы в
 * его /local/log, а не в каталог логов этого модуля.
 *
 * Тест подключает настоящий include.php и спрашивает функции, а не ищет
 * строку require в исходнике.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';

$sandbox = sys_get_temp_dir().'/shef-problems-include-'.getmypid();
\Bitrix\Main\Application::$documentRoot = $sandbox;

// shef.options при подключении объявляет свои _log(), _log1(), _pr() — ровно
// как на портале. Файл свой, с тем же function_exists, что и в настоящем:
// чья версия победит, решает только порядок в include.php.
$optionsFunctions = $sandbox.'-options-def-functions.php';
file_put_contents($optionsFunctions, <<<'PHP'
<?php
foreach(['_log', '_log1', '_pr'] as $name)
{
	if(!function_exists($name))
	{
		eval('function '.$name.'(...$args): void {}');
	}
}
PHP);
\Bitrix\Main\Loader::$onInclude['shef.options'] = static function() use ($optionsFunctions): void
{
	require_once $optionsFunctions;
};

Check::group('до подключения модуля');

Check::same('_log ещё нет', function_exists('_log'), false);
Check::same('_log1 ещё нет', function_exists('_log1'), false);
Check::same('_pr ещё нет', function_exists('_pr'), false);

require_once $root.'/include.php';

Check::group('после подключения модуля');

foreach(['_log', '_log1', '_pr'] as $name)
{
	Check::same($name.' объявлена этим модулем', (new ReflectionFunction($name))->getFileName(), $root.'/def-functions.php');
}

Check::group('сигнатура _log та, что зовёт трейт Log из shef.options');

// TraitList\Log::log() зовёт _log($value, static::getLogFile()) — массив и
// строка. Победит версия этого модуля — вызов обязан работать с ней.
$log = new ReflectionFunction('_log');
Check::same('_log принимает два аргумента', $log->getNumberOfParameters(), 2);
Check::same('первый — массив', (string)$log->getParameters()[0]->getType(), 'array');
Check::same('второй — строка', (string)$log->getParameters()[1]->getType(), 'string');
Check::same('оба со значением по умолчанию', $log->getNumberOfRequiredParameters(), 0);

Check::group('_log и _log1 пишут в каталог логов модуля');

_log(['шаг' => 1], 'include-test');
_log(['шаг' => 2], 'include-test');
$file = $sandbox.'/local/sh_log/include-test.log';
$text = is_file($file) ? (string)file_get_contents($file) : '';
Check::same('_log дописывает', substr_count($text, '[шаг] =>'), 2);

_log1(['первый' => 1], 'include-test1');
_log1(['второй' => 2], 'include-test1');
$text = (string)file_get_contents($sandbox.'/local/sh_log/include-test1.log');
Check::same('_log1: первый вызов перезаписал, второй дописал', [str_contains($text, '[первый]'), str_contains($text, '[второй]')], [true, true]);

Check::group('_pr экранирует');

\Bitrix\Main\Engine\CurrentUser::$isAdmin = true;
ob_start();
_pr(['name' => '<b>жирный</b>']);
$out = (string)ob_get_clean();
Check::same('разметка из данных не проходит', str_contains($out, '<b>жирный</b>'), false);

// region Уборка ////
unlink($optionsFunctions);
array_map('unlink', glob($sandbox.'/local/sh_log/*') ?: []);
rmdir($sandbox.'/local/sh_log');
rmdir($sandbox.'/local');
rmdir($sandbox);
// endregion ////

Check::finish();
