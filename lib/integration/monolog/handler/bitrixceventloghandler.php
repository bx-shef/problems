<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use CEventLog;
use LogicException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Entity\BitrixCEventLogEntity;
use Shef\Problems\Integration\Monolog\Formatter\BitrixCEventLogFormatter;
use Monolog\LogRecord;

/**
 * Пишет лог в \CEventLog
 *
 * Настройка очистка журнала в главном модуле
 * Можно добавить оповещения через в админке Б24
 *
 * @see CEventLog
 * @see /bitrix/admin/event_log.php
 * @see /bitrix/admin/log_notification_edit.php
 * @see b_event_log
 */
class BitrixCEventLogHandler
	extends AbstractProcessingHandler
{
	public function __construct(
		public readonly string $auditType,
		int|string|Level $level = Level::Debug,
		bool $bubble = true
	)
	{
		parent::__construct($level, $bubble);
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function write(LogRecord $record): void
	{
		$formatted = $record->formatted;
		if(!($formatted instanceof BitrixCEventLogEntity))
		{
			throw new LogicException(sprintf(
				'LogRecord->formatted not implement %s. Try use %s',
				BitrixCEventLogEntity::class,
				BitrixCEventLogFormatter::class
			));
		}
		
		CEventLog::Log(
			$formatted->getSeverity(),
			$this->auditType,
			$formatted->getModuleId(),
			$formatted->getItemId(),
			$formatted->getFormattedDescription()
		);
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultFormatter(): FormatterInterface
	{
		return new BitrixCEventLogFormatter();
	}
}