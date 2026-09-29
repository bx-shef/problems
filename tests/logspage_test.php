<?php declare(strict_types=1);

/**
 * Страница логов admin/logs.php: права, путь, экранирование — сама страница,
 * а не только класс LogFiles.
 *
 * Каталог логов вынесли за корень сайта, и эта страница — единственное окно
 * к нему из браузера. logfiles_test.php держит LogFiles::resolve(), но
 * страница могла бы его не звать, не проверять администратора или печатать
 * лог без экранирования — и ни один тест не покраснел бы (так показали
 * мутации панели ревью). Здесь подключается настоящий admin/logs.php.
 *
 * Пролог административной части — пустые файлы в песочнице, $APPLICATION и
 * $USER — заглушки ниже. AuthForm() на портале показывает форму входа и
 * завершает скрипт; заглушка бросает исключение — дальше страница не идёт.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;

// region Заглушки административной части ////
final class AuthFormShown extends RuntimeException {}

class CUser
{
	public function __construct(private readonly bool $isAdmin) {}

	public function IsAdmin(): bool
	{
		return $this->isAdmin;
	}
}

class CMain
{
	public ?string $title = null;

	public function SetTitle(?string $title): void
	{
		$this->title = $title;
	}

	public function AuthForm(?string $message): never
	{
		throw new AuthFormShown((string)$message);
	}
}

class CAdminMessage
{
	public static function ShowMessage(?string $message): void
	{
		echo '[message]', $message, '[/message]';
	}

	public static function ShowNote(?string $message): void
	{
		echo '[note]', $message, '[/note]';
	}
}

class CFile
{
	public static function FormatSize(int|float $size): string
	{
		return $size.' B';
	}
}
// endregion ////

// region Песочница: корень сайта и каталог логов рядом с ним ////
$portal = sys_get_temp_dir().'/shef-problems-logspage-'.getmypid();
$www = $portal.'/www';
mkdir($www.'/bitrix/modules/main/include', 0777, true);
mkdir($portal.'/sh_log');
foreach(['prolog_admin_before', 'prolog_admin_after', 'epilog_admin'] as $part)
{
	file_put_contents($www.'/bitrix/modules/main/include/'.$part.'.php', '<?php');
}
file_put_contents($www.'/bitrix/.settings.php', 'СЕКРЕТ ПОРТАЛА');
file_put_contents($portal.'/sh_log/sync.log', "до\n<script>alert(1)</script>\nпосле\n");

$_SERVER['DOCUMENT_ROOT'] = $www;
\Bitrix\Main\Application::$documentRoot = $www;
Loc::loadLangFile($root.'/lang/ru/admin/logs.php');
// endregion ////

/**
 * Открыть страницу.
 *
 * @return array{title: ?string, out: string, auth: bool}
 */
$open = static function(array $query, bool $isAdmin = true) use ($root): array
{
	Context::$query = $query;
	$GLOBALS['USER'] = new CUser($isAdmin);
	$GLOBALS['APPLICATION'] = new CMain();

	$auth = false;
	ob_start();
	try
	{
		include $root.'/admin/logs.php';
	}
	catch(AuthFormShown)
	{
		$auth = true;
	}
	$out = (string)ob_get_clean();

	return ['title' => $GLOBALS['APPLICATION']->title, 'out' => $out, 'auth' => $auth];
};

Check::group('права');

$page = $open(['file' => 'sync.log'], false);
Check::same('не администратор — форма входа', $page['auth'], true);
Check::same('и ни строки лога', str_contains($page['out'], 'alert'), false);

$page = $open([]);
Check::same('администратор — без формы входа', $page['auth'], false);
Check::same('список: файл в нём', str_contains($page['out'], '>sync.log</a>'), true);

Check::group('содержимое экранировано');

$page = $open(['file' => 'sync.log']);
Check::same('разметки из лога нет', str_contains($page['out'], '<script>'), false);
Check::same('она видна текстом', str_contains($page['out'], '&lt;script&gt;alert(1)&lt;/script&gt;'), true);

Check::group('имя из запроса — только через resolve()');

$page = $open(['file' => '../www/bitrix/.settings.php']);
Check::same('выход из каталога логов — «файла нет»', str_contains($page['out'], 'СЕКРЕТ'), false);
Check::same('сообщение «файла нет»', str_contains($page['out'], '[message]Файла'), true);

$page = $open(['file' => ['sync.log']]);
Check::same('?file[]= — не имя: список, без warning', str_contains($page['out'], '>sync.log</a>'), true);

Check::group('заголовок экранирован');

// Имя приходит из запроса, а заголовок уходит в страницу как HTML: ссылка
// с ?file=<img …> — отражённый XSS под администратором.
$page = $open(['file' => '<img src=x onerror=alert(1)>']);
Check::same('в заголовке разметки нет', $page['title'], 'Лог &lt;img src=x onerror=alert(1)&gt;');
Check::same('в теле — тоже', str_contains($page['out'], '<img'), false);

Check::group('языковой файл');

preg_match_all("/Loc::getMessage\\('([A-Z0-9_]+)'/", (string)file_get_contents($root.'/admin/logs.php'), $codes);
Check::same(
	'каждый код страницы переведён',
	array_values(array_diff(array_unique($codes[1]), array_keys(Loc::$messages))),
	[]
);

// region Уборка ////
Context::$query = [];
Loc::$messages = [];
exec('rm -rf '.escapeshellarg($portal));
// endregion ////

Check::finish();
