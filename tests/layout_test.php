<?php

declare(strict_types=1);

/**
 * install/layout.php (bx-shef/options#55): раскладка для портала из git — ровно то, что кладёт
 * установщик. Копии — те же, что installDir в .settings.php; заглушка — текст AdminPage для
 * модуля в /bitrix/modules и в /local/modules; файл подключается без ядра.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

$make = include $root.'/install/layout.php';
$settings = include $root.'/.settings.php';

foreach (['/bitrix/modules', '/local/modules'] as $base) {
    $layout = $make('/var/www', '/var/www'.$base.'/shef.problems');
    Check::same('копии = installDir', $layout['copy'], array_map(static fn (array $d): array => ['from' => $d['from'], 'to' => $d['to']], $settings['installDir']['value']));
    Check::same('заглушка логов из '.$base, $layout['files'], [
        '/bitrix/admin/shef_problems_logs.php' => \Shef\Problems\Main\AdminPage::getContent('/var/www', '/var/www'.$base.'/shef.problems'),
    ]);
}
Check::same('заглушка ведёт в модуль', str_contains((string)current($layout['files']), "/local/modules/shef.problems/admin/logs.php'"), true);

exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('var_export(is_callable(include "'.$root.'/install/layout.php"));'), $out, $code);
Check::same('без ядра и заглушек', [$code, implode('', $out)], [0, 'true']);

Check::finish();
