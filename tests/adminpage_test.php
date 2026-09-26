<?php declare(strict_types=1);

/**
 * Заглушка страницы логов в /bitrix/admin: путь — куда модуль стоит на самом
 * деле, удаляется — только своя.
 *
 * * Модуль из /local/modules с заглушкой, зашитой на /bitrix/modules, дал бы
 *   белую страницу. Поэтому заглушку пишут, а не копируют: установщик и меню
 *   знают каталог модуля.
 * * В /bitrix/admin лежат файлы всех модулей, и на месте нашей заглушки
 *   проект мог положить свой файл. Деинсталляция не вправе его снести, а
 *   установка — перезаписать.
 *
 * Держит \Shef\Problems\Main\AdminPage; подключается он явным require_once —
 * ровно как в установщике.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';
require_once $root.'/lib/main/adminpage.php';

use Shef\Problems\Main\AdminPage;

$portal = sys_get_temp_dir().'/shef-problems-adminpage-'.getmypid();
$www = $portal.'/www';
mkdir($www.'/bitrix/admin', 0777, true);
$target = AdminPage::getTarget($www);

$require = static fn(string $path): string => "<?php require(\$_SERVER['DOCUMENT_ROOT'].'".$path."');\n";

Check::group('путь — туда, где стоит модуль');

Check::same(
	'/bitrix/modules',
	AdminPage::getContent($www, $www.'/bitrix/modules/shef.problems'),
	$require('/bitrix/modules/shef.problems/admin/logs.php')
);
Check::same(
	'/local/modules',
	AdminPage::getContent($www, $www.'/local/modules/shef.problems'),
	$require('/local/modules/shef.problems/admin/logs.php')
);
Check::same(
	'вне корня сайта — абсолютный путь',
	AdminPage::getContent($www, '/opt/modules/shef.problems'),
	"<?php require('/opt/modules/shef.problems/admin/logs.php');\n"
);
Check::same(
	'сосед корня сайта с тем же началом имени — тоже вне корня',
	AdminPage::getContent($www, $www.'-old/bitrix/modules/shef.problems'),
	"<?php require('".$www."-old/bitrix/modules/shef.problems/admin/logs.php');\n"
);

// Заглушка должна быть рабочим PHP, а не только похожей на него строкой.
$probe = $portal.'/probe.php';
file_put_contents($probe, AdminPage::getContent($www, $www.'/local/modules/shef.problems'));
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($probe).' 2>&1', $lint, $code);
Check::same('заглушка — синтаксически верный PHP', $code, 0);

Check::group('своя или чужая');

Check::same('своя — /bitrix/modules', AdminPage::isOwn($require('/bitrix/modules/shef.problems/admin/logs.php')), true);
Check::same('своя — /local/modules', AdminPage::isOwn($require('/local/modules/shef.problems/admin/logs.php')), true);
Check::same('своя — абсолютный путь', AdminPage::isOwn("<?php require('/opt/m/shef.problems/admin/logs.php');\n"), true);
Check::same('чужая — другой модуль', AdminPage::isOwn($require('/bitrix/modules/acme.logs/admin/logs.php')), false);
Check::same('чужая — своя страница проекта', AdminPage::isOwn("<?php\nrequire \$_SERVER['DOCUMENT_ROOT'].'/local/admin/logs.php';\necho 1;"), false);
Check::same('чужая — наш require плюс ещё код', AdminPage::isOwn($require('/bitrix/modules/shef.problems/admin/logs.php').'<?php echo 1;'), false);

Check::group('установка');

Check::same('файла нет — пишет', AdminPage::install($www, $www.'/local/modules/shef.problems'), true);
Check::same('путь из /local/modules', (string)file_get_contents($target), $require('/local/modules/shef.problems/admin/logs.php'));

// Модуль переехали — своя заглушка переписывается.
Check::same('модуль переехал — переписывает', AdminPage::install($www, $www.'/bitrix/modules/shef.problems'), true);
Check::same('путь новый', (string)file_get_contents($target), $require('/bitrix/modules/shef.problems/admin/logs.php'));

file_put_contents($target, '<?php // своя страница проекта');
Check::same('чужой файл — не трогает', AdminPage::install($www, $www.'/bitrix/modules/shef.problems'), false);
Check::same('чужой файл цел', (string)file_get_contents($target), '<?php // своя страница проекта');

Check::same('нет /bitrix/admin — не создаёт его', AdminPage::install($portal.'/нет', $www.'/bitrix/modules/shef.problems'), false);

Check::group('удаление');

$moduleDir = $www.'/bitrix/modules/shef.problems';
Check::same('чужой файл — не удаляет', [AdminPage::uninstall($www, $moduleDir), is_file($target)], [false, true]);

AdminPage::install($www, $www.'/bitrix/modules/shef.problems');
unlink($target);
AdminPage::install($www, $www.'/bitrix/modules/shef.problems');
Check::same('своя — удаляет', [AdminPage::uninstall($www, $moduleDir), is_file($target)], [true, false]);
Check::same('уже нет — не ошибка', AdminPage::uninstall($www, $moduleDir), true);

// Каталог модуля назван не shef.problems (например, клон репозитория), а
// заглушку писал он сам — она своя.
AdminPage::install($www, '/opt/problems');
Check::same('своя — по точному совпадению', [AdminPage::uninstall($www, '/opt/problems'), is_file($target)], [true, false]);

// region Уборка ////
exec('rm -rf '.escapeshellarg($portal));
// endregion ////

Check::finish();
