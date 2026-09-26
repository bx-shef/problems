<?php

/**
 * Автозагрузка для тестов и примеров без портала.
 *
 * Два правила, и оба — те же, по которым классы ищет портал:
 *
 * * Shef\Problems\Foo\Bar -> lib/foo/bar.php, путь СТРОЧНЫМИ. Так
 *   \Bitrix\Main\Loader отображает классы модуля; держит это соглашение
 *   tests/autoload_test.php;
 * * Monolog\... -> своя копия в vendor/monolog/monolog/src/Monolog — та, что
 *   регистрирует .settings.php, когда Monolog нет в Composer проекта.
 *
 * Классы модуля подключаются настоящие: тест, проверяющий свою копию логики,
 * ничего не проверяет.
 */

require_once __DIR__.'/bitrix.php';
require_once __DIR__.'/psr.php';

spl_autoload_register(static function(string $class): void
{
	$root = dirname(__DIR__, 2);

	$map = [
		'Shef\\Problems\\' => static fn(string $rest): string => $root.'/lib/'.mb_strtolower(str_replace('\\', '/', $rest)).'.php',
		'Monolog\\' => static fn(string $rest): string => $root.'/vendor/monolog/monolog/src/Monolog/'.str_replace('\\', '/', $rest).'.php',
	];

	foreach($map as $prefix => $toPath)
	{
		if(!str_starts_with($class, $prefix))
		{
			continue;
		}

		$path = $toPath(substr($class, strlen($prefix)));
		if(is_file($path))
		{
			require_once $path;
		}

		return;
	}
});
