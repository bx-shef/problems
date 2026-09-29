<?php declare(strict_types=1);

namespace Shef\Problems\Factory;

use Monolog\Level as MonologLevel;
use Monolog\LogRecord as MonologLogRecord;
use Shef\Problems\Integration\Monolog\Logger;
use Shef\Problems\Integration\Monolog\Handler;
use Shef\Problems\Integration\Monolog\Processor;
use Shef\Problems\Main\Constants;

/**
 * Фабрика построения логгеров для Problems/Sync/Product/Sale
 */
class SystemLoggerFactory
{
	public static function build(
		MonologLevel $logLevel,
		string $auditType = Constants::AuditTypeProblem,
		string $moduleId = Constants::EmptyValue,
		string $className = Constants::EmptyValue,
		null|int $assigned = null
	): Logger
	{
		if(!in_array($auditType, Constants::getAuditTypeList()))
		{
			$auditType = Constants::getDefAuditType();
		}
		
		if(null === $assigned)
		{
			$assigned = Constants::getDefUserId();
		}
		
		return (new Logger('problems'))
			->pushHandler(
				// Потолок размера — как у отладочного лога: цикл сбоев в агенте
				// пишет проблему на каждой итерации.
				(new Handler\CappedStreamHandler(
					filename: Constants::getLogFullPath(mb_strtolower($auditType)),
					level: $logLevel
				))
				->setFormatter(Constants::getDefaultFormatter())
			)
			->pushHandler(
				(new Handler\BitrixCEventLogHandler(
					auditType: $auditType,
					level: $logLevel
				))
			)
			->pushProcessor(new Processor\TraceProcessor(false))
			->pushProcessor(
				function (MonologLogRecord $record)
				use ($moduleId, $className, $assigned)
				{
					// Модуль не задан фабрике — остаётся тот, что передан в
					// контексте записи: иначе в журнал уходил бы 'empty'.
					// Нет нигде — 'empty', как раньше.
					if(
						Constants::EmptyValue !== $moduleId
						|| !isset($record->context['moduleId'])
					)
					{
						$record->extra['moduleId'] = $moduleId;
					}
					$record->extra['class'] = $className;
					$record->extra['assigned'] = $assigned;
					
					return $record;
				}
			)
		;
	}
}