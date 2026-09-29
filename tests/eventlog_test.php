<?php declare(strict_types=1);

/**
 * Запись в журнал событий Битрикса (CEventLog).
 *
 * Главное здесь — важность. Журнал знает пять значений SEVERITY: SECURITY,
 * ERROR, WARNING, INFO, DEBUG; всё прочее CEventLog::Add() пишет как
 * UNKNOWN. В 1.1.7 туда шло имя уровня Monolog как есть, и CRITICAL, ALERT,
 * EMERGENCY — самое важное, что модуль вообще пишет, — ложились в журнал
 * «неизвестными» и не находились фильтром по важности.
 *
 * Дальше — что попадает в поля журнала: тип события от фабрики, модуль и
 * ID элемента из контекста, остальное — в описание.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Monolog\Level;
use Shef\Problems\Factory\SystemLoggerFactory;
use Shef\Problems\Integration\Monolog\Entity\BitrixCEventLogEntity;
use Shef\Problems\Integration\Monolog\Handler\BitrixCEventLogHandler;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Main\Constants;

// Корень сайта — www внутри песочницы: каталог логов лежит на уровень выше
// корня, то есть тоже в песочнице.
$sandbox = sys_get_temp_dir().'/shef-problems-eventlog-'.getmypid();
mkdir($sandbox.'/www', 0777, true);
\Bitrix\Main\Application::$documentRoot = $sandbox.'/www';

Check::group('уровень Monolog -> SEVERITY журнала');

$expected = [
	'Debug' => 'DEBUG',
	'Info' => 'INFO',
	'Notice' => 'INFO',
	'Warning' => 'WARNING',
	'Error' => 'ERROR',
	'Critical' => 'ERROR',
	'Alert' => 'ERROR',
	'Emergency' => 'ERROR',
];

$allowed = ['SECURITY', 'ERROR', 'WARNING', 'INFO', 'DEBUG'];
$outside = [];

foreach(Level::cases() as $level)
{
	$severity = BitrixCEventLogEntity::mapSeverity($level);
	Check::same($level->name, $severity, $expected[$level->name]);

	if(!in_array($severity, $allowed, true))
	{
		$outside[] = $level->name;
	}
}

Check::same('каждый уровень ложится в то, что журнал знает', $outside, []);

Check::group('обработчик: поля журнала');

CEventLog::$records = [];

$logger = (new Logger('problems'))
	->pushHandler(new BitrixCEventLogHandler(auditType: Constants::AuditTypeSync));

$logger->critical('Выгрузка остановилась', [
	'itemId' => 42,
	'moduleId' => 'acme.exchange',
	'file' => 'orders.xml',
]);

$record = CEventLog::$records[0] ?? [];

Check::same('записано одно событие', count(CEventLog::$records), 1);
Check::same('SEVERITY', $record['SEVERITY'] ?? null, 'ERROR');
Check::same('тип события — от обработчика', $record['AUDIT_TYPE_ID'] ?? null, Constants::AuditTypeSync);
Check::same('модуль — из контекста', $record['MODULE_ID'] ?? null, 'acme.exchange');
Check::same('ID элемента — из контекста', $record['ITEM_ID'] ?? null, 42);
Check::same('исходный уровень не потерян — он в описании', str_contains($record['DESCRIPTION'] ?? '', '[problems->CRITICAL]'), true);
Check::same('прочий контекст — в описании', str_contains($record['DESCRIPTION'] ?? '', '{file} = {orders.xml}'), true);

Check::group('фабрика: файл, журнал и кто ответственный');

CEventLog::$records = [];
\Bitrix\Main\Config\Option::$values = [];
\Bitrix\Main\Config\Option::set(Constants::MODULE_ID, 'DEF_defuserid', '17');

$logger = SystemLoggerFactory::build(
	logLevel: Level::Warning,
	auditType: Constants::AuditTypeProduct,
	moduleId: 'acme.catalog',
	className: 'Acme\\Catalog\\Import',
);

$logger->info('ниже порога — никуда');
$logger->error('Нет цены у товара', ['itemId' => 7]);

$file = Constants::getLogFullPath(mb_strtolower(Constants::AuditTypeProduct));
$text = is_file($file) ? (string)file_get_contents($file) : '';
$record = CEventLog::$records[0] ?? [];

Check::same('ниже уровня фабрики не пишется', count(CEventLog::$records), 1);
Check::same('в журнале — тип от фабрики', $record['AUDIT_TYPE_ID'] ?? null, Constants::AuditTypeProduct);
Check::same('файл лога назван по типу', is_file($file), true);
Check::same('и лежит вне корня сайта', $file, $sandbox.'/sh_log/sh_problems_product.log');
Check::same('в файле — сообщение', str_contains($text, 'Нет цены у товара'), true);
Check::same('ответственный по умолчанию — из настроек', str_contains($text, '"assigned":17'), true);
Check::same('класс записан', str_contains($text, 'Acme\\\\Catalog\\\\Import'), true);

Check::group('фабрика: чужой тип события');

CEventLog::$records = [];
$logger = SystemLoggerFactory::build(logLevel: Level::Debug, auditType: 'SOMETHING_ELSE');
$logger->error('куда-то');

Check::same('незнакомый тип заменён типом по умолчанию', CEventLog::$records[0]['AUDIT_TYPE_ID'] ?? null, Constants::getDefAuditType());

Check::group('фабрика: модуль из контекста записи');

CEventLog::$records = [];
SystemLoggerFactory::build(logLevel: Level::Debug)->error('без модуля у фабрики', ['moduleId' => 'acme.exchange']);
Check::same('фабрике модуль не задан — берётся из контекста', CEventLog::$records[0]['MODULE_ID'] ?? null, 'acme.exchange');

CEventLog::$records = [];
SystemLoggerFactory::build(logLevel: Level::Debug, moduleId: 'acme.catalog')->error('модуль у фабрики');
Check::same('фабрике модуль задан — он', CEventLog::$records[0]['MODULE_ID'] ?? null, 'acme.catalog');

// region Уборка ////
$remove = static function(string $dir) use (&$remove): void
{
	foreach(scandir($dir) ?: [] as $entry)
	{
		if('.' === $entry || '..' === $entry)
		{
			continue;
		}

		is_dir($dir.'/'.$entry) ? $remove($dir.'/'.$entry) : unlink($dir.'/'.$entry);
	}

	rmdir($dir);
};
$remove($sandbox);
// endregion ////

Check::finish();
