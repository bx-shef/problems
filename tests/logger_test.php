<?php declare(strict_types=1);

/**
 * Логгер модуля принимает не только строку.
 *
 * \Shef\Problems\Integration\Monolog\Logger — наследник \Monolog\Logger,
 * которому первым аргументом можно отдать исключение, Result, Error, массив и
 * прочее: стратегия превращает это в сообщение и контекст. Здесь зафиксировано,
 * во что именно, — это и есть контракт, на который опираются вызывающие.
 *
 * Плюс enum \Shef\Problems\Logger: какие логгеры пишут в файл, решает
 * настоящий .settings.php, а меню административной части строится по этому
 * ответу.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Type\Contract\Arrayable;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;
use Shef\Problems\Logger as LoggerEnum;
use Shef\Problems\Main\Constants;

$handler = new TestHandler(Level::Debug);
$logger = (new Logger('test'))->pushHandler($handler);

/** Сообщение и контекст последней записи. */
$last = static function() use ($handler): array
{
	$records = $handler->getRecords();
	$record = end($records);

	return [$record->message, $record->context];
};

Check::group('строка и Stringable');

$logger->info('просто текст', ['a' => 1]);
Check::same('строка', $last(), ['просто текст', ['a' => 1]]);

$logger->info(new class implements Stringable { public function __toString(): string { return 'из объекта'; } });
Check::same('Stringable', $last()[0], 'из объекта');

Check::group('исключение');

$exception = new RuntimeException('Нет связи с 1С');
$logger->error($exception, ['step' => 'orders']);
[$message, $context] = $last();

Check::same('сообщение — текст исключения', $message, 'Нет связи с 1С');
Check::same('само исключение — в контексте', $context[LoggerConverter\ThrowableStrategy::ContextKey] ?? null, $exception);
Check::same('свой контекст сохранён', $context['step'] ?? null, 'orders');

Check::group('Error и Result ядра');

$logger->warning(new Error('Не заполнено поле', 'EMPTY_FIELD'));
[$message, $context] = $last();
Check::same('Error: сообщение с кодом', $message, 'Не заполнено поле [code: EMPTY_FIELD]');
Check::same('Error: в контексте', $context[LoggerConverter\BitrixErrorStrategy::ContextKey][0]['code'] ?? null, 'EMPTY_FIELD');

$result = (new Result())
	->addError(new Error('первая', 1))
	->addError(new Error('вторая', 2));
$logger->error($result);
[$message, $context] = $last();
Check::same('Result с ошибками: первая и счётчик', $message, '[Result::Error: 2] первая [code: 1]');
Check::same('Result: все ошибки в контексте', count($context[LoggerConverter\BitrixResultStrategy::ContextKey][0]['error'] ?? []), 2);

$logger->info((new Result())->setData(['id' => 5]));
[$message, $context] = $last();
Check::same('Result успешный', $message, '[Result::Success]');
Check::same('Result: данные в контексте', $context[LoggerConverter\BitrixResultStrategy::ContextKey][0]['data'] ?? null, ['id' => 5]);

Check::group('массив и контракты');

$logger->debug(['a' => 1]);
Check::same('массив', $last(), ['Array', ['_message' => ['a' => 1]]]);

$logger->debug(new class implements Arrayable { public function toArray(): array { return ['b' => 2]; } });
Check::same('Arrayable', $last(), ['Arrayable', ['_message' => ['b' => 2]]]);

$logger->debug(new class implements JsonSerializable { public function jsonSerialize(): mixed { return ['c' => 3]; } });
Check::same('JsonSerializable', $last(), ['JsonSerializable', ['_message' => ['c' => 3]]]);

Check::group('то, что не превратить');

Check::throws('число', InvalidArgumentException::class, fn() => $logger->info(42));
Check::throws('объект без контракта', InvalidArgumentException::class, fn() => $logger->info(new stdClass()));

// Стратегия, которой подсунули чужой тип, отвечает внятным исключением, а не
// TypeError из собственной проверки.
Check::throws(
	'ThrowableStrategy на строке',
	InvalidArgumentException::class,
	fn() => (new LoggerConverter\ThrowableStrategy())->doMessage('не исключение')
);

Check::group('сбой записи не роняет вызывающий код');

// Каталог логов вне open_basedir или без прав — StreamHandler бросает
// UnexpectedValueException. Здесь «каталог» — обычный файл: mkdir под ним не
// выйдет ни у кого, в том числе у root.
$sandbox = sys_get_temp_dir().'/shef-problems-logger-'.getmypid();
mkdir($sandbox);
file_put_contents($sandbox.'/not-a-dir', '');
$phpLog = $sandbox.'/php.log';
$previousErrorLog = ini_set('error_log', $phpLog);

$broken = (new Logger('broken'))->pushHandler(
	new Shef\Problems\Integration\Monolog\Handler\CappedStreamHandler($sandbox.'/not-a-dir/sh_log/x.log')
);
$thrown = null;
try
{
	$broken->error('обмен упал');
}
catch(Throwable $throwable)
{
	$thrown = $throwable::class;
}
ini_set('error_log', (string)$previousErrorLog);

Check::same('исключения наружу нет', $thrown, null);
Check::same(
	'сбой — в лог PHP, с именем логгера',
	str_contains((string)@file_get_contents($phpLog), 'shef.problems: запись логгера broken не прошла: UnexpectedValueException'),
	true
);

exec('rm -rf '.escapeshellarg($sandbox));

Check::group('enum Logger и сервисы из .settings.php');

\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';

$names = static fn(array $cases): array => array_map(static fn(LoggerEnum $case): string => $case->name, $cases);

Check::same('пишут в файл', $names(LoggerEnum::getEnumUsedInterface(Constants::HandlerTypeFile)), ['Log1', 'Log', 'Problems', 'Deprecations']);
Check::same('выводят на экран', $names(LoggerEnum::getEnumUsedInterface(Constants::HandlerTypePrint)), ['Pr', 'PrHtml']);
Check::same('пишут в журнал событий', $names(LoggerEnum::getEnumUsedInterface(Constants::HandlerTypeBitrixEventLog)), ['Problems']);
Check::throws('незнакомый тип обработчика', InvalidArgumentException::class, fn() => LoggerEnum::getEnumUsedInterface('telegram'));
Check::throws('Problems — фабрика, а не логгер', LogicException::class, fn() => LoggerEnum::Problems->getLogger());

Check::finish();
