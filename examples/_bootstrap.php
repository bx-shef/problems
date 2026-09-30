<?php

declare(strict_types=1);

/**
 * Обвязка примеров. Сама по себе примером не является.
 *
 * Примеры должны запускаться в двух мирах, и поэтому обвязка одна:
 *
 * * НА ПОРТАЛЕ — ядро настоящее. Подключается пролог Битрикса и модуль,
 *   дальше классы даёт автозагрузка, и код примера — ровно тот, что вы
 *   напишете у себя.
 * * БЕЗ ПОРТАЛА — ядро подменяется заглушками из tests/stub/bitrix.php, а
 *   файлы классов подключаются руками. Так примеры гоняет CI.
 *
 * Мир выбирается по DOCUMENT_ROOT: указали — значит портал.
 *
 *   php examples/<имя>.php                                # заглушки
 *   DOCUMENT_ROOT=/var/www/portal php examples/<имя>.php  # живой Битрикс
 *
 * Обвязка даёт примерам две вещи: step()/note() для рассказа и check() —
 * обещание примера. Пример не рассказывает, что получится, а показывает:
 * разойдётся обещание с результатом — пример упадёт с ненулевым кодом
 * возврата.
 *
 * Подключать классы руками примерам не нужно: на портале их находит
 * автозагрузка Битрикса, без портала — tests/stub/autoload.php по тому же
 * соглашению (Shef\Problems\Foo -> lib/foo.php) плюс своя копия Monolog.
 */

$root = dirname(__DIR__);

$documentRoot = (string)(getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));
$prolog = $documentRoot.'/bitrix/modules/main/include/prolog_before.php';

/** @var string $exampleMode где мы выполняемся: «портал» или «заглушки» */
$exampleMode = 'заглушки';

if ('' !== $documentRoot && is_file($prolog)) {
    // region Живой портал ////
    $exampleMode = 'портал';

    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;

    // Примеры ничего не показывают пользователю и не должны попадать в
    // статистику: это скрипт командной строки, а не страница.
    if (!defined('NO_KEEP_STATISTIC')) {
        define('NO_KEEP_STATISTIC', true);
    }

    if (!defined('NOT_CHECK_PERMISSIONS')) {
        define('NOT_CHECK_PERMISSIONS', true);
    }

    require_once $prolog;

    if (!\Bitrix\Main\Loader::includeModule('shef.problems')) {
        fwrite(STDERR, 'Модуль shef.problems не установлен на портале '.$documentRoot.PHP_EOL);
        exit(1);
    }
    // endregion ////
} else {
    // region Без портала ////
    require_once $root.'/tests/stub/autoload.php';

    // Сервисы модуля берутся из настоящего .settings.php — ровно их на
    // портале отдаёт ServiceLocator.
    \Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';

    // Песочница вместо сайта: корень — <песочница>/www, а логи модуля пишутся
    // на уровень выше корня, в <песочница>/sh_log. Обвязка за собой всё
    // убирает. На портале корень и каталог логов настоящие.
    $sandbox = sys_get_temp_dir().'/shef-problems-examples-'.getmypid();
    mkdir($sandbox.'/www', 0777, true);
    \Bitrix\Main\Application::$documentRoot = $sandbox.'/www';

    register_shutdown_function(static function () use ($sandbox): void {
        $remove = static function (string $dir) use (&$remove): void {
            if (!is_dir($dir)) {
                return;
            }

            foreach (scandir($dir) ?: [] as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                $path = $dir.'/'.$entry;

                if (is_link($path) || is_file($path)) {
                    unlink($path);
                    continue;
                }

                $remove($path);
            }

            rmdir($dir);
        };

        $remove($sandbox);
    });

    unset($sandbox);
    // endregion ////
}

/**
 * Warning и notice — провал, а не строчка в выводе: пример, засоряющий лог
 * портала, примером быть не может.
 *
 * Ставится ПОСЛЕ пролога: у Битрикса свой обработчик, и перехватывать его
 * загрузку мы не собираемся.
 */
set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    // Заглушённое «@» — не провал: так же поступает и ядро.
    if (!(error_reporting() & $level)) {
        return false;
    }

    throw new ErrorException($message, 0, $level, $file, $line);
});

$failed = 0;

/** Заголовок шага. */
function step(string $title): void
{
    echo PHP_EOL, '— ', $title, PHP_EOL;
}

/** Пояснение без проверки. */
function note(string $text): void
{
    echo '  ', $text, PHP_EOL;
}

/**
 * Обещание примера: что получится на самом деле.
 *
 * Единственное, что отличает пример от рассказа о примере.
 */
function check(string $what, mixed $actual, mixed $expected): void
{
    global $failed;

    $show = static fn (mixed $value): string => is_object($value)
        ? get_class($value)
        : var_export($value, true);

    if ($actual === $expected) {
        printf("  ok   %s = %s%s", $what, $show($actual), PHP_EOL);
        return;
    }

    $failed++;
    printf("  FAIL %s: получено %s, обещано %s%s", $what, $show($actual), $show($expected), PHP_EOL);
}

/** Шапка примера. */
function title(string $name): void
{
    global $exampleMode;

    printf('%s [%s]%s', $name, $exampleMode, PHP_EOL);
}

/** Итог. Ненулевой код возврата означает, что пример разошёлся с кодом. */
function done(string $name): never
{
    global $failed;

    echo PHP_EOL;

    if ($failed > 0) {
        printf('%s: расхождений %d%s', $name, $failed, PHP_EOL);
        exit(1);
    }

    printf('ГОТОВО: %s%s', $name, PHP_EOL);
    exit(0);
}
