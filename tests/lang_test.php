<?php declare(strict_types=1);

/**
 * У каждой подписи есть перевод: код из Loc::getMessage('X') в файле модуля
 * лежит в lang/ru/<тот же путь>.
 *
 * Заглушка Loc в тестах без загруженных сообщений отдаёт сам код, поэтому
 * пропавший ключ ни один тест не видит, а на портале это пустой пункт меню
 * или пустой заголовок: ядро на незнакомый код отдаёт null. Коды MAIN_* —
 * сообщения ядра, их модуль не переводит.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$skip = ['vendor/', 'tests/', 'lang/', 'examples/', '.claude/'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

$checked = 0;
$missing = [];
foreach($files as $file)
{
	$path = substr($file->getPathname(), strlen($root) + 1);
	if('php' !== $file->getExtension() || array_filter($skip, static fn(string $dir): bool => str_starts_with($path, $dir)))
	{
		continue;
	}

	// Только литерал целиком: 'SH_X'.$code собирается в рантайме, его не сверить.
	preg_match_all("/Loc::getMessage\\(\\s*'([A-Z0-9_]+)'\\s*[,)]/", (string)file_get_contents($file->getPathname()), $found);
	$codes = array_values(array_filter(array_unique($found[1]), static fn(string $code): bool => !str_starts_with($code, 'MAIN_')));
	if(empty($codes))
	{
		continue;
	}

	$MESS = [];
	$langFile = $root.'/lang/ru/'.$path;
	if(is_file($langFile))
	{
		include $langFile;
	}

	foreach($codes as $code)
	{
		$checked++;
		if(!isset($MESS[$code]) || '' === $MESS[$code])
		{
			$missing[] = $path.': '.$code;
		}
	}
}

Check::group('подписи переведены');

Check::same('кодов найдено — проверка не впустую', $checked > 20, true);
Check::same('у каждого кода есть перевод', $missing, []);

Check::finish();
