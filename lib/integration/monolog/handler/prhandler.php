<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Bitrix\Main\Engine;

/**
 * Выводит запись на экран. По умолчанию — только администратору.
 *
 * Всё, что пришло из записи, экранируется: в сообщение и контекст попадает
 * что угодно, в том числе ввод пользователя, а вывод идёт прямо в страницу.
 * Без экранирования это хранимый XSS в браузере администратора — ровно того,
 * кому вывод и показывают.
 */
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
		if($this->isShowForAll)
		{
			return true;
		}

		return Engine\CurrentUser::get()->isAdmin() === true;
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

	/**
	 * Трассировку кладёт TraceProcessor. Без него ключа нет вовсе, и это не
	 * ошибка: обработчик можно собрать и без трассировки.
	 */
	protected function makeStackTraces(LogRecord $record): string
	{
		$list = $record->extra['trace'] ?? '';
		if(is_string($list))
		{
			return static::escape($list);
		}
		elseif(is_array($list))
		{
			return join(
				static::getSeparator(),
				array_map(
					static fn(mixed $line): string => static::escape((string)$line),
					$list
				)
			);
		}

		return '';
	}

	protected function makeInfo(LogRecord $record): string
	{
		return sprintf(
			'<pre>%s</pre>',
			static::escape(print_r($this->prepareRecordInfo($record), true))
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

	protected static function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
