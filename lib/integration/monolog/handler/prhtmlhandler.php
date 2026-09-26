<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Bitrix\Main\LoaderException;
use Monolog\LogRecord;
use Bitrix\Main\UI\Extension;
use Shef\Problems\Main\Constants;

/**
 * Вывод на экран с разметкой: цвет блока по уровню записи.
 *
 * Стили — расширение Constants::EXTENSION_PR_HTML, его раскладывает
 * установщик в /bitrix/js. Экранирование — в родителе.
 */
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
			Constants::EXTENSION_PR_HTML,
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
			static::escape($record->level->getName()),
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
