<?php

declare(strict_types=1);

/**
 * Своя копия Monolog: та, что обещана, и без deprecation на новом PHP.
 *
 * Monolog приезжает двумя путями — Composer проекта и своей копией в
 * vendor/ для установки архивом (решение владельца, оба пути оставлены).
 * Две копии одного и того же расходятся молча, поэтому здесь:
 *
 * 1. версия своей копии подходит под ограничение из composer.json — иначе
 *    поставленный архивом и поставленный Composer модуль работали бы на
 *    разном Monolog;
 * 2. копия не даёт deprecation при разборе файлов. Monolog 3.3.1, лежавший
 *    здесь до 2.0.0, на PHP 8.4 сыпал «Implicitly marking parameter as
 *    nullable» прямо из Monolog\Logger — в лог портала, на каждый запрос с
 *    логгером. CI гоняет это на каждой версии PHP из матрицы: новая версия
 *    PHP с новыми deprecation покраснеет здесь, а не на портале;
 * 3. копия целая: лицензия на месте, классы, которые зовёт модуль, есть.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$vendor = $root.'/vendor/monolog/monolog';

Check::group('версия своей копии');

// Версию несёт первая запись CHANGELOG.md: в composer.json пакета её нет.
preg_match('/^### (\d+)\.(\d+)\.(\d+)/m', (string)@file_get_contents($vendor.'/CHANGELOG.md'), $version);
Check::same('версия прочитана из CHANGELOG.md', count($version), 4);

[, $major, $minor, $patch] = array_map('intval', $version + [0, 0, 0, 0]);

$composer = json_decode((string)file_get_contents($root.'/composer.json'), true);
$constraint = (string)($composer['require']['monolog/monolog'] ?? '');

// Разбираем ровно ту форму, что стоит в composer.json: ^X.Y.
Check::same('ограничение вида ^X.Y', 1 === preg_match('/^\^(\d+)\.(\d+)$/', $constraint, $want), true);

$satisfies = $major === (int)($want[1] ?? -1) && $minor >= (int)($want[2] ?? PHP_INT_MAX);
Check::same(
    sprintf('копия %d.%d.%d подходит под %s', $major, $minor, $patch, $constraint),
    $satisfies,
    true
);

Check::group('разбор файлов без deprecation');

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendor.'/src'));
foreach ($iterator as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        $files[] = $file->getPathname();
    }
}
sort($files);

Check::same('файлов Monolog больше сотни', count($files) > 100, true);

$noisy = [];
foreach ($files as $file) {
    $output = [];
    exec(
        sprintf('%s -d error_reporting=-1 -d display_errors=1 -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)),
        $output
    );

    foreach ($output as $line) {
        if (preg_match('/\b(Deprecated|Warning|Notice)\b/', $line)) {
            $noisy[] = mb_substr($file, mb_strlen($root) + 1).': '.$line;
            break;
        }
    }
}

Check::same('ни одного deprecation на PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, $noisy, []);

Check::group('копия целая');

Check::same('лицензия MIT на месте', str_contains((string)@file_get_contents($vendor.'/LICENSE'), 'Permission is hereby granted'), true);

$used = [
    'Logger.php',
    'Level.php',
    'LogRecord.php',
    'Utils.php',
    'Handler/AbstractProcessingHandler.php',
    'Handler/StreamHandler.php',
    'Formatter/LineFormatter.php',
    'Formatter/NormalizerFormatter.php',
    'Processor/ProcessorInterface.php',
];
$missing = array_values(array_filter($used, static fn (string $path): bool => !is_file($vendor.'/src/Monolog/'.$path)));

Check::same('всё, что зовёт модуль, на месте', $missing, []);

Check::finish();
