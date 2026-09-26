<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Bitrix\Main\LoaderException;
use Monolog\LogRecord;
use Bitrix\Main\UI\Extension;

class PrHtmlHandler
	extends PrHandler
{
	protected static function getSeparator(): string
	{
		return PHP_EOL.'<br>';
	}
	
	/**
	 * @throws LoaderException
	 */
	protected function initCss(): void
	{
		Extension::load([
			'shef-problems.monolog-pr-html'
		]);
	}
	
	/**
	 * @throws LoaderException
	 */
	protected function makeWrite(LogRecord $record): string
	{
		$this->initCss();
		
		return sprintf(
			'<div class="shef-problems-container" data-level="%s">%s</div>',
			$record->level->getName(),
			parent::makeWrite($record)
		);
	}
	
	protected function makeStackTraces(LogRecord $record): string
	{
		return sprintf(
			'<div>%s</div>',
			parent::makeStackTraces($record)
		);
	}
}