<?php declare(strict_types=1);

/**
 * Вывод на экран: только администратору и только экранированным.
 *
 * PrHandler и PrHtmlHandler печатают запись прямо в страницу. В сообщение и
 * контекст попадает что угодно — в том числе то, что прислал посетитель: имя
 * из формы, заголовок запроса, поле лида. В 1.1.7 это шло в <pre> как есть,
 * то есть хранимый XSS в браузере администратора — ровно того, кому этот
 * вывод и показывают.
 *
 * Отдельно: без TraceProcessor ключа trace в extra нет вовсе, и чтение его
 * без проверки давало warning на каждой записи.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\UI\Extension;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Handler\PrHandler;
use Shef\Problems\Integration\Monolog\Handler\PrHtmlHandler;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Processor\TraceProcessor;
use Shef\Problems\Main\Constants;

/** Что обработчик напечатал за вызов. */
$printed = static function(callable $call): string
{
	ob_start();
	$call();
	return (string)ob_get_clean();
};

$attack = '<script>alert(1)</script>';

Check::group('кому показывать');

CurrentUser::$isAdmin = false;
$logger = (new Logger('pr'))->pushHandler(new PrHandler());
Check::same('не администратору — ничего', $printed(fn() => $logger->debug('секрет')), '');

$logger = (new Logger('pr'))->pushHandler(new PrHandler(isShowForAll: true));
Check::same('isShowForAll — всем', str_contains($printed(fn() => $logger->debug('всем')), 'всем'), true);

CurrentUser::$isAdmin = true;
$logger = (new Logger('pr'))->pushHandler(new PrHandler());
Check::same('администратору — да', str_contains($printed(fn() => $logger->debug('админу')), 'админу'), true);

Check::group('PrHandler: экранирование');

$out = $printed(fn() => $logger->warning($attack, ['name' => $attack]));

Check::same('тега script в выводе нет', str_contains($out, '<script>'), false);
Check::same('сообщение экранировано', str_contains($out, '&lt;script&gt;alert(1)&lt;/script&gt;'), true);
Check::same('обёртка <pre> — своя разметка — на месте', str_starts_with($out, '<pre>'), true);

Check::group('PrHandler: без TraceProcessor');

// Обвязка превращает warning в исключение: дошли досюда — ключ не читали вслепую.
$out = $printed(fn() => $logger->info('без трассировки'));
Check::same('запись без extra.trace напечатана', str_contains($out, 'без трассировки'), true);

Check::group('PrHtmlHandler: разметка своя, данные чужие');

Extension::$loaded = [];

$handler = new PrHtmlHandler();
$handler->pushProcessor(new TraceProcessor());
$logger = (new Logger('prHtml'))->pushHandler($handler);

$out = $printed(fn() => $logger->error($attack, ['field' => $attack]));

Check::same('тега script в выводе нет', str_contains($out, '<script>'), false);
Check::same('контейнер с уровнем', str_starts_with($out, '<div class="shef-problems-container" data-level="ERROR">'), true);
Check::same('строки трассировки разделены <br>', str_contains($out, PHP_EOL.'<br>'), true);
Check::same('стили подключены', Extension::$loaded, [Constants::EXTENSION_PR_HTML]);

// Трассировка — тоже данные: в пути может оказаться что угодно.
$out = $printed(function() use ($logger): void
{
	$logger->debug('трасса', []);
});
Check::same('в трассировке нет сырых угловых скобок из данных', str_contains($out, '<script'), false);

Check::finish();
