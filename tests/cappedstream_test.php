<?php declare(strict_types=1);

/**
 * Лог с потолком размера: история есть, диск не забивается.
 *
 * Отладочный лог на живом портале рос без предела и забивал диск. В рабочей
 * копии 1.1.7 это лечили переводом на Log1Handler — но тот стирает файл на
 * каждом запросе, и Log превращался в Log1. CappedStreamHandler дописывает,
 * пока файл меньше потолка, а перерос — откладывает его в <имя>.1 и начинает
 * новый. На диске не больше двух потолков на файл, даже без logrotate.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Handler\CappedStreamHandler;
use Shef\Problems\Integration\Monolog\Logger;

$dir = sys_get_temp_dir().'/shef-problems-capped-'.getmypid();
mkdir($dir, 0777, true);
$file = $dir.'/log.log';

$logger = static function(int $maxBytes) use ($file): Logger
{
	return (new Logger('log'))->pushHandler(
		(new CappedStreamHandler(filename: $file, level: Level::Debug, maxBytes: $maxBytes))
			->setFormatter(new LineFormatter('%message%'.PHP_EOL))
	);
};

Check::group('ниже потолка — дописывает, как обычный лог');

$log = $logger(1000);
$log->debug('первая');
$log->debug('вторая');

Check::same('обе записи в файле', (string)file_get_contents($file), 'первая'.PHP_EOL.'вторая'.PHP_EOL);

// Новый «запрос» — новый обработчик: история не стирается (в отличие от Log1).
$logger(1000)->debug('третья');
Check::same('новый запрос дописал, а не стёр', substr_count((string)file_get_contents($file), PHP_EOL), 3);

Check::group('перерос потолок — откладывает и начинает заново');

$log = $logger(100);
for($i = 1; $i <= 40; $i++)
{
	$log->debug(sprintf('строка %02d', $i));
}

clearstatcache();
$current = (string)file_get_contents($file);
$previous = is_file($file.'.1') ? (string)file_get_contents($file.'.1') : '';

Check::same('отложенный файл есть', is_file($file.'.1'), true);
Check::same('текущий файл не больше потолка с одной записью сверху', strlen($current) < 100 + 64, true);
Check::same('отложенный — тоже', strlen($previous) < 100 + 64, true);
Check::same('последняя запись — в текущем', str_ends_with($current, 'строка 40'.PHP_EOL), true);
Check::same('лишних файлов нет — только текущий и .1', count(glob($dir.'/*') ?: []), 2);

Check::group('потолок 0 — без ограничения');

$log = $logger(0);
for($i = 1; $i <= 40; $i++)
{
	$log->debug(sprintf('строка %02d', $i));
}
unlink($file.'.1');
$log->debug('ещё');
Check::same('без потолка не откладывает', is_file($file.'.1'), false);

// region Уборка ////
\Bitrix\Main\IO\Directory::deleteDirectory($dir);
// endregion ////

Check::finish();
