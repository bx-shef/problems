<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;

/**
 * Файл лога с потолком размера: дописывает, как StreamHandler, но перерос
 * потолок — откладывает файл в <имя>.1 (прежний .1 затирается) и начинает
 * новый.
 *
 * Зачем. Отладочный лог на живом портале рос без предела и забивал диск: в
 * рабочей копии 1.1.7 его перевели на Log1Handler, но тот стирает файл на
 * каждом запросе — и Log становился копией Log1, в файле оставался только
 * последний запрос. Потолок решает то же, не меняя смысла: история есть, а
 * место на диске ограничено — не больше двух потолков на файл, даже если
 * logrotate на сервере не настроен.
 *
 * Другие процессы, пишущие в тот же файл, переименование увидят сами:
 * StreamHandler с Monolog 3.10 переоткрывает файл, когда у пути сменился
 * inode.
 */
class CappedStreamHandler
	extends StreamHandler
{
	/** Потолок по умолчанию — 20 МБ. */
	public const DEFAULT_MAX_BYTES = 20 * 1024 * 1024;

	protected string $filename;

	public function __construct(
		string $filename,
		int|string|Level $level = Level::Debug,
		public readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
		bool $bubble = true,
		?int $filePermission = null,
		bool $useLocking = false
	)
	{
		$this->filename = Utils::canonicalizePath($filename);
		parent::__construct($this->filename, $level, $bubble, $filePermission, $useLocking);
	}

	/**
	 * @inheritDoc
	 */
	protected function write(LogRecord $record): void
	{
		if($this->isOverLimit())
		{
			$this->close();
			$this->rotate();
		}

		parent::write($record);
	}

	protected function isOverLimit(): bool
	{
		if($this->maxBytes <= 0)
		{
			return false;
		}

		clearstatcache(true, $this->filename);

		return is_file($this->filename) && (int)filesize($this->filename) >= $this->maxBytes;
	}

	/**
	 * <имя> -> <имя>.1. Два процесса могут переименовывать одновременно:
	 * проигравший получит warning от rename() — его глушим, файл уже отложен.
	 */
	protected function rotate(): void
	{
		set_error_handler(static fn(): bool => true);
		try
		{
			rename($this->filename, $this->filename.'.1');
		}
		finally
		{
			restore_error_handler();
		}
	}
}
