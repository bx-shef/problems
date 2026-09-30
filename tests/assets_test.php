<?php

declare(strict_types=1);

/**
 * Фронт: имя расширения сходится с раскладкой установщика.
 *
 * Каталог модуля браузеру недоступен, поэтому install/js установщик кладёт
 * в /bitrix/js (карта в .settings.php, ключ installDir). Ядро находит
 * расширение по имени «<каталог>.<подкаталог>» в /bitrix/js. Разойдутся имя
 * в Constants и раскладка — стили молча не подключатся, ошибки не будет: в
 * админке это выглядит как «логи без цвета», а не как поломка установки.
 *
 * Тест разворачивает имя расширения обратно в файл репозитория по карте
 * installDir — ровно тем путём, которым файл доедет до портала.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Shef\Problems\Main\Constants;

$settings = require $root.'/.settings.php';

/** Куда установщик кладёт install/js. */
$jsMap = array_values(array_filter(
    $settings['installDir']['value'],
    static fn (array $map): bool => $map['from'] === '/install/js'
));

Check::group('раскладка');

Check::same('install/js копируется ровно одной записью', count($jsMap), 1);
Check::same('в /bitrix/js', $jsMap[0]['to'] ?? null, '/bitrix/js');
Check::same('каталог расширений — через дефис', Constants::getPublicJsDir(), '/bitrix/js/shef-problems');

/**
 * Публичный путь расширения -> файл config.php в репозитории.
 */
$toRepo = static function (string $extension) use ($root, $jsMap): string {
    $public = '/bitrix/js/'.str_replace('.', '/', $extension);

    return $root.$jsMap[0]['from'].mb_substr($public, mb_strlen($jsMap[0]['to'])).'/config.php';
};

Check::group('каждое расширение на месте');

$broken = [];
$relBroken = [];

foreach (Constants::getExtensionList() as $extension) {
    if (!str_starts_with('/bitrix/js/'.str_replace('.', '/', $extension), Constants::getPublicJsDir().'/')) {
        $broken[] = $extension.': не в каталоге модуля';
        continue;
    }

    $config = $toRepo($extension);
    if (!is_file($config)) {
        $broken[] = $extension.': нет '.mb_substr($config, mb_strlen($root));
        continue;
    }

    // config.php расширения требует пролог.
    if (!defined('B_PROLOG_INCLUDED')) {
        define('B_PROLOG_INCLUDED', true);
    }

    $description = require $config;

    foreach ((array)($description['css'] ?? []) as $css) {
        if (!is_file(dirname($config).'/'.$css)) {
            $broken[] = $extension.': нет стиля '.$css;
        }
    }

    foreach ((array)($description['rel'] ?? []) as $rel) {
        if (str_starts_with($rel, 'shef-problems.') && !in_array($rel, Constants::getExtensionList(), true)) {
            $relBroken[] = $extension.' -> '.$rel;
        }
    }
}

Check::same('config.php и стили каждого расширения существуют', $broken, []);
Check::same('зависимости между своими расширениями — из списка', $relBroken, []);

Check::group('в репозитории нет лишних расширений');

$onDisk = array_map(
    static fn (string $path): string => 'shef-problems.'.basename(dirname($path)),
    glob($root.'/install/js/shef-problems/*/config.php') ?: []
);
sort($onDisk);
$declared = Constants::getExtensionList();
sort($declared);

Check::same('на диске ровно то, что объявлено', $onDisk, $declared);

Check::finish();
