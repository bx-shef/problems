<?php declare(strict_types=1);

/**
 * Учёт проблем в своём классе: трейт LoggerProblems.
 *
 * ЦЕЛЬ
 *   Показать главный способ пользоваться модулем: класс вашего модуля
 *   подключает трейт \Shef\Problems\Factory\Trait\LoggerProblems, говорит,
 *   кто он и о чём его проблемы, — и пишет их одной строкой. Запись ложится
 *   сразу в два места: в файл лога по типу проблемы и в журнал событий
 *   Битрикса, с ответственным из настроек модуля.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Импорт, выгрузка, обработчики событий, агенты — везде, где сбой надо не
 *   только записать, но и найти потом в журнале событий по типу («проблемы с
 *   синхронизацией», «с товаром», «с продажами») и понять, кому он адресован.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: problems», код возврата 0.
 *   По сути:
 *     * запись ниже уровня логгера не пишется никуда;
 *     * запись уровнем Error и выше — в файл <тип>.log и в журнал событий;
 *     * в журнале: тип события, модуль и ID элемента из контекста, важность
 *       ERROR — даже для CRITICAL, потому что журнал знает только пять
 *       значений важности;
 *     * в файле — класс, модуль и ответственный.
 *
 * ЗАПУСК
 *   php examples/problems.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/problems.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Пример пишет по-настоящему: одну запись в журнал событий (тип
 *   SH_PROBLEMS_SYNC, модуль acme.exchange) и строку в sh_problems_sync.log
 *   каталога логов (на BitrixVM — /home/bitrix/sh_log). Убирать их пример не станет — это и
 *   есть то, что вы потом найдёте через меню «Учёт проблем». Проверки
 *   журнала на портале пропускаются: читать b_event_log пример не берётся.
 */

require_once __DIR__.'/_bootstrap.php';

title('Учёт проблем через трейт LoggerProblems');

use Monolog\Level;
use Shef\Problems\Factory\Trait\LoggerProblems;
use Shef\Problems\Main\Constants;

// region Ваш класс ////
/**
 * Так выглядит класс вашего модуля. Обязательны два метода — кто пишет и из
 * какого модуля; уровень, тип проблемы и ответственный — по желанию.
 */
final class OrdersExchange
{
	use LoggerProblems;

	public function __construct()
	{
		$this->initLogger();
	}

	public static function getClassName(): string
	{
		return static::class;
	}

	public static function getModuleId(): string
	{
		return 'acme.exchange';
	}

	/** Ниже Error — не проблема, а шум: не пишем. */
	protected static function getLogLevel(): Level
	{
		return Level::Error;
	}

	/** Все проблемы этого класса — про синхронизацию. */
	protected static function getAuditType(): string
	{
		return Constants::AuditTypeSync;
	}

	/** Ответственный — тот, кто в настройках модуля отвечает за синхронизации. */
	public static function getAssignedId(): int
	{
		return Constants::getSyncUserId();
	}

	public function run(): void
	{
		$this->logger->info('Началась выгрузка заказов');
		$this->logger->critical('1С не ответила за 30 секунд', [
			'itemId' => 1024,
			'moduleId' => static::getModuleId(),
		]);
	}
}
// endregion ////

step('Класс пишет проблемы');

$isStub = 'заглушки' === $exampleMode;

if($isStub)
{
	\Bitrix\Main\Config\Option::set(Constants::MODULE_ID, 'DEF_syncuserid', '15');
	CEventLog::$records = [];
}

(new OrdersExchange())->run();

$file = Constants::getLogFullPath(mb_strtolower(Constants::AuditTypeSync));
$text = is_file($file) ? (string)file_get_contents($file) : '';

check('файл лога по типу проблемы создан', is_file($file), true);
check('в файле — критическая запись', str_contains($text, 'problems.CRITICAL: 1С не ответила за 30 секунд'), true);
check('info ниже уровня — в файл не попал', str_contains($text, 'Началась выгрузка заказов'), false);
check('в файле — класс', str_contains($text, '"class":"OrdersExchange"'), true);

step('Журнал событий');

if($isStub)
{
	$record = CEventLog::$records[0] ?? [];

	check('в журнал ушла одна запись', count(CEventLog::$records), 1);
	check('тип события', $record['AUDIT_TYPE_ID'] ?? null, Constants::AuditTypeSync);
	check('модуль — из контекста', $record['MODULE_ID'] ?? null, 'acme.exchange');
	check('ID элемента — из контекста', $record['ITEM_ID'] ?? null, 1024);
	check('важность — та, что журнал знает', $record['SEVERITY'] ?? null, 'ERROR');
	check('ответственный из настроек', str_contains($text, '"assigned":15'), true);
}
else
{
	note('Журнал: Настройки → Инструменты → Журнал событий, тип SH_PROBLEMS_SYNC.');
	note('Или меню «Учёт проблем» → «Журнал событий» → «[Monolog] Sync».');
}

done('problems');
