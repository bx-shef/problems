<?php declare(strict_types=1);

/**
 * Страница логов отдаёт только файлы каталога логов.
 *
 * Каталог логов лежит вне корня сайта — чтобы веб-сервер его не отдавал. Лог
 * смотрят страницей /bitrix/admin/shef_problems_logs.php, и имя файла приходит
 * в неё параметром запроса. Если его не проверить, страница стала бы ровно тем
 * окном наружу, которое закрыли выносом каталога: ?file=../www/bitrix/.settings.php.
 *
 * Держит \Shef\Problems\Main\LogFiles:
 *
 * * имя — по шаблону, без «/» и «..»;
 * * путь после realpath() — внутри каталога логов: ссылка с допустимым
 *   именем, ведущая наружу, отсекается;
 * * префикс сверяется с разделителем: соседу sh_log-old каталог sh_log не
 *   подходит;
 * * большой файл читается с конца и с целой первой строкой.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Shef\Problems\Main\LogFiles;

$sandbox = sys_get_temp_dir().'/shef-problems-logfiles-'.getmypid();
$dir = $sandbox.'/sh_log';
mkdir($dir, 0777, true);
mkdir($sandbox.'/sh_log-old', 0777, true);
mkdir($sandbox.'/www/bitrix', 0777, true);

file_put_contents($dir.'/log.log', "строка 1\nстрока 2\n");
file_put_contents($dir.'/sh_problems_sync.log.1', "после logrotate\n");
file_put_contents($dir.'/sh_problems_sync.log.2.gz', 'сжатый');
file_put_contents($dir.'/notes.txt', 'не лог');
file_put_contents($sandbox.'/www/bitrix/.settings.php', '<?php return ["секрет"];');
file_put_contents($sandbox.'/sh_log-old/old.log', 'соседний каталог');
symlink($sandbox.'/www/bitrix/.settings.php', $dir.'/settings.log');
symlink($sandbox.'/sh_log-old/old.log', $dir.'/neighbour.log');

$files = new LogFiles($dir);

Check::group('имена');

$accepted = array_values(array_filter(
	['log.log', 'sh_problems_sync.log', 'sh_problems_sync.log.1', 'log1-custom.log'],
	static fn(string $name): bool => LogFiles::isValidName($name)
));
Check::same('обычные имена и ротированные проходят', count($accepted), 4);

$rejected = array_values(array_filter(
	[
		'../www/bitrix/.settings.php',
		'../sh_log-old/old.log',
		'/etc/passwd',
		'sub/log.log',
		'log.log/../../x.log',
		'.log',
		'log.log.2.gz',
		'notes.txt',
		"log.log\0.php",
		"log.log\n",
		'',
	],
	static fn(string $name): bool => LogFiles::isValidName($name)
));
Check::same('всё остальное — нет', $rejected, []);

Check::group('путь после разрешения ссылок');

Check::same('свой файл', $files->resolve('log.log'), realpath($dir.'/log.log'));
Check::same('ротированный', $files->resolve('sh_problems_sync.log.1'), realpath($dir.'/sh_problems_sync.log.1'));
Check::same('ссылка с именем лога, ведущая к настройкам сайта', $files->resolve('settings.log'), null);
Check::same('ссылка в соседний каталог sh_log-old', $files->resolve('neighbour.log'), null);
Check::same('выход наверх', $files->resolve('../www/bitrix/.settings.php'), null);
Check::same('файла нет', $files->resolve('missing.log'), null);
Check::same('файл есть, но имя не лога', $files->resolve('notes.txt'), null);
mkdir($dir.'/folder.log');
Check::same('каталог с именем лога', $files->resolve('folder.log'), null);
rmdir($dir.'/folder.log');

// Каталог логов сам — ссылка: префикс сверяется с разрешённым каталогом, иначе
// не нашлось бы ни одного файла.
symlink($dir, $sandbox.'/sh_log-link');
Check::same('каталог логов — ссылка: свой файл находится', (new LogFiles($sandbox.'/sh_log-link'))->resolve('log.log'), realpath($dir.'/log.log'));
unlink($sandbox.'/sh_log-link');

Check::group('список');

Check::same(
	'только настоящие логи внутри каталога, по имени',
	array_column($files->getList(), 'name'),
	['log.log', 'sh_problems_sync.log.1']
);
Check::same('размер', $files->getList()[0]['size'], strlen("строка 1\nстрока 2\n"));
Check::same('каталога нет — пустой список', (new LogFiles($sandbox.'/нет'))->getList(), []);

Check::group('хвост файла');

$small = $files->tail((string)$files->resolve('log.log'));
Check::same('маленький файл — целиком', [$small['content'], $small['truncated']], ["строка 1\nстрока 2\n", false]);

$big = $dir.'/big.log';
$lines = [];
for($i = 1; $i <= 1000; $i++)
{
	$lines[] = sprintf('строка %04d', $i);
}
file_put_contents($big, implode("\n", $lines)."\n");

$tail = $files->tail($big, 200);
$shown = explode("\n", rtrim($tail['content'], "\n"));

Check::same('большой файл — обрезан', $tail['truncated'], true);
Check::same('последняя строка на месте', end($shown), 'строка 1000');
Check::same('первая строка целая, не обрывок', 1 === preg_match('/^строка \d{4}$/u', $shown[0]), true);
Check::same('не больше заказанного', strlen($tail['content']) <= 200, true);
Check::same('размер — всего файла', $tail['size'], (int)filesize($big));

// region Уборка ////
\Bitrix\Main\IO\Directory::deleteDirectory($sandbox);
// endregion ////

Check::finish();
