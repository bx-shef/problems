<?php

declare(strict_types=1);

/**
 * composer.json: решения, за которые заплачено в shef.options.
 *
 * composer validate (он в CI) проверяет, что файл — правильный манифест.
 * Здесь другое: что он правильный ДЛЯ МОДУЛЯ БИТРИКСА. Каждая строка ниже —
 * ловушка, которая не видна ни validate, ни глазами:
 *
 * * тип bitrix-module, а не bitrix-d7-module: installer-name подменяет
 *   только {$name}, и d7 развернул бы модуль в
 *   bitrix/modules/bxshef.shef.problems/ — каталог, которого ядро не знает.
 *   Так было в 1.1.7: пакет shef/problems, тип bitrix-d7-module;
 * * потолок composer/installers: bitrix-module помечен deprecated, в v3 его
 *   уберут, и без потолка модуль молча уехал бы в чужой каталог;
 * * вендор bxshef: shef на Packagist занят чужим пакетом;
 * * Monolog — настоящая зависимость, а не нестандартный ключ, которого
 *   Composer не знает (в 1.1.7 был require_to_module_vendor);
 * * shef.options — через свой пакет bxshef/options.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$composer = json_decode((string)file_get_contents($root.'/composer.json'), true);

Check::group('пакет');

Check::same('composer.json разобран', is_array($composer), true);
Check::same('имя', $composer['name'] ?? null, 'bxshef/problems');
Check::same('тип', $composer['type'] ?? null, 'bitrix-module');
Check::same('installer-name — id модуля', $composer['extra']['installer-name'] ?? null, 'shef.problems');
Check::same('лицензия в тон LICENSE', $composer['license'] ?? null, 'MIT');
Check::same('LICENSE — MIT', str_starts_with((string)file_get_contents($root.'/LICENSE'), 'MIT License'), true);

Check::group('зависимости');

$require = $composer['require'] ?? [];

Check::same('потолок composer/installers', $require['composer/installers'] ?? null, '^1.0 || ^2.0');
Check::same('shef.options — своим пакетом', $require['bxshef/options'] ?? null, '^3.0');
Check::same('Monolog — настоящей зависимостью', isset($require['monolog/monolog']), true);
Check::same('нестандартных ключей нет', array_key_exists('require_to_module_vendor', $composer), false);
Check::same('shef.uiclear не нужен', array_key_exists('shef/uiclear', $require) || array_key_exists('bxshef/uiclear', $require), false);

Check::group('версия PHP — одна на всех');

$installer = (string)file_get_contents($root.'/install/index.php');
preg_match("/PHP_MIN_VER = '([0-9.]+)'/", $installer, $phpMin);

Check::same('установщик и composer.json требуют один PHP', '>='.($phpMin[1] ?? ''), $require['php'] ?? null);

Check::group('ветка разработки — текущая мажорная');

$arModuleVersion = [];
require $root.'/install/version.php';
$major = (int)explode('.', (string)($arModuleVersion['VERSION'] ?? '0'))[0];

Check::same('branch-alias', $composer['extra']['branch-alias']['dev-main'] ?? null, $major.'.x-dev');

Check::finish();
