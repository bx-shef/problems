<?php declare(strict_types=1);

/**
 * Трассировка записи лога: без warning и с первой строкой там, где позвали.
 *
 * Две поломки 1.1.7, и обе тихие:
 *
 * 1. Throwable\TraceRow\Row читал ключи кадра без проверки. У кадра
 *    встроенной функции нет file и line, у вызова функции нет class и type —
 *    и КАЖДАЯ запись лога с TraceProcessor давала на PHP 8 «Undefined array
 *    key». Не падало, засоряло лог — у логгера, который от этого и спасает.
 * 2. TraceProcessor отрезал ровно шесть кадров. Шесть — это глубина до
 *    процессора, повешенного на обработчик. Повешенный на логгер, он
 *    отрезал бы кадры вызывающего кода, и первая строка трассировки
 *    указывала бы не туда.
 *
 * Ядро — заглушки, Monolog — своя копия модуля, классы модуля настоящие.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Processor\TraceProcessor;
use Shef\Problems\Throwable\TraceRow\Row;
use Shef\Problems\Throwable\TraceRow\Formatter;

\Bitrix\Main\Application::$documentRoot = $root;

Check::group('Row: кадр без ключей');

// Кадр встроенной функции: только function. assert.php превращает warning
// в исключение, так что сам факт конструирования — уже проверка.
$row = new Row(['function' => 'array_map']);

Check::same('файла нет — пометка', $row->file, '[internal function]');
Check::same('строки нет — null', $row->line, null);
Check::same('класса нет — пусто', $row->class, '');
Check::same('типа вызова нет — пусто', $row->type, '');

$row = new Row([]);
Check::same('пустой кадр тоже разбирается', $row->function, '');

Check::group('Row: полный кадр');

$row = new Row([
	'file' => $root.'/local/php_interface/init.php',
	'line' => 12,
	'function' => 'handle',
	'class' => 'Acme\\Demo',
	'type' => '->',
]);

Check::same('корень сайта срезан', $row->file, '/local/php_interface/init.php');
Check::same('строка', $row->line, 12);
Check::same('Full', (new Formatter\Full())->getLine($row, 0), '#0 /local/php_interface/init.php [line:12]: Acme\\Demo->handle()');
Check::same('Simple', (new Formatter\Simple())->getLine($row), 'File: /local/php_interface/init.php [line: 12]');

Check::group('TraceProcessor: первая строка — место вызова');

/**
 * Первая строка трассировки последней записи.
 */
$firstLine = static function(TestHandler $handler): string
{
	$records = $handler->getRecords();
	$trace = end($records)->extra['trace'] ?? [];

	return (string)($trace[0] ?? '');
};

// Процессор на обработчике — как в сервисах .settings.php.
$handler = new TestHandler(Level::Debug);
$handler->pushProcessor(new TraceProcessor(false));
$logger = (new Logger('test'))->pushHandler($handler);

$line = __LINE__ + 1;
$logger->debug('на обработчике');
Check::same('процессор на обработчике', $firstLine($handler), 'File: /tests/trace_test.php [line: '.$line.']');

// Тот же процессор на логгере: глубина вызова другая, результат — тот же.
$handler = new TestHandler(Level::Debug);
$logger = (new Logger('test'))->pushHandler($handler)->pushProcessor(new TraceProcessor(false));

$line = __LINE__ + 1;
$logger->info('на логгере');
Check::same('процессор на логгере', $firstLine($handler), 'File: /tests/trace_test.php [line: '.$line.']');

// Через log() — ещё одна глубина.
$line = __LINE__ + 1;
$logger->log(Level::Warning, 'через log()');
Check::same('через log()', $firstLine($handler), 'File: /tests/trace_test.php [line: '.$line.']');

// Из функции: первым идёт место вызова логгера, а не функции.
$callFromFunction = static function() use ($logger): int
{
	$line = __LINE__ + 1;
	$logger->notice('из функции');
	return $line;
};

$line = $callFromFunction();
Check::same('из функции', $firstLine($handler), 'File: /tests/trace_test.php [line: '.$line.']');

Check::group('TraceProcessor: исключение даёт свою трассировку');

$handler = new TestHandler(Level::Debug);
$handler->pushProcessor(new TraceProcessor(true));
$logger = (new Logger('test'))->pushHandler($handler);

$throwing = static function(): never
{
	throw new RuntimeException('сбой');
};

try
{
	$throwing();
}
catch(RuntimeException $exception)
{
	$logger->error($exception);
}

$records = $handler->getRecords();
$trace = end($records)->extra['trace'];

Check::same('трассировка исключения — массив', is_array($trace), true);
Check::same('первая строка — из трассировки исключения', str_starts_with((string)$trace[0], '#0 /tests/trace_test.php'), true);
Check::same('сообщение — текст исключения', end($records)->message, 'сбой');

Check::finish();
