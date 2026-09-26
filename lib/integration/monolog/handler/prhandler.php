<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Bitrix\Main\Engine;

class PrHandler
	extends AbstractProcessingHandler
{
	protected static function getSeparator(): string
	{
		return PHP_EOL;
	}
	
	public function __construct(
		public readonly bool $isShowForAll = false,
		int|string|Level $level = Level::Debug,
		bool $bubble = true
	)
	{
		parent::__construct($level, $bubble);
	}
	
	protected function write(LogRecord $record): void
	{
		if(!$this->isStart())
		{
			return;
		}
		
		echo $this->makeWrite($record);
	}
	
	protected function isStart(): bool
	{
		if(!(
			Engine\CurrentUser::get()->isAdmin()
			|| $this->isShowForAll
		))
		{
			return false;
		}
		
		return true;
	}
	
	protected function makeWrite(LogRecord $record): string
	{
		return $this->makeContent($record);
	}
	
	protected function makeContent(LogRecord $record): string
	{
		return
			$this->makeStackTraces($record)
			.$this->makeInfo($record);
	}
	
	protected function makeStackTraces(LogRecord $record): string
	{
		$list = $record->extra['trace'] ?: '';
		if(is_string($list))
		{
			return $list;
		}
		elseif(is_array($list))
		{
			return join(static::getSeparator(), $list);
		}
		
		return '';
	}
	
	protected function makeInfo(LogRecord $record): string
	{
		return sprintf(
			'<pre>%s</pre>',
			print_r($this->prepareRecordInfo($record), true)
		);
	}
	
	protected function prepareRecordInfo(LogRecord $record): array
	{
		$extra = $record->extra;
		unset($extra['trace']);
		
		return [
			'title' => sprintf(
				'%s >> [%s %s] >> %s',
				$record->message,
				$record->channel,
				$record->level->getName(),
				$record->datetime->format('d.m.Y H:i:s')
			),
			'context' => $record->context,
			'extra' => $extra,
		];
	}
}