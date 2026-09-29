<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;

/**
 * Пишет лог в файл, при первом обращении удаляет файл.
 *
 * Полезно если в логах нужно смотреть только нужную цепочку
 */
class Log1Handler
	extends StreamHandler
{
	protected string $filename;
	protected bool|null $mustRotate = null;
	
	public function __construct(
		string $filename,
		int|string|Level $level = Level::Debug,
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
		// on the first record written, if the log is new, we should rotate
		if(null === $this->mustRotate)
		{
			$this->mustRotate = true;
		}
		
		if($this->mustRotate)
		{
			$this->close();
		}
		
		parent::write($record);
	}
	
	/**
	 * @inheritDoc
	 */
	public function close(): void
	{
		parent::close();
		
		if(true === $this->mustRotate)
		{
			$this->rotate();
		}
	}
	
	/**
	 * @return void
	 *
	 * @memo suppress errors here as unlink() might fail if two processes are cleaning up/rotating at the same time
	 */
	protected function rotate(): void
	{
		// true — ошибка обработана и дальше не идёт; false отдал бы её
		// штатному обработчику, и warning всё равно вышел бы. is_writable()
		// тоже внутри: вне open_basedir warning даёт и он.
		set_error_handler(callback: function(int $errno, string $errstr, string $errfile, int $errline): bool
		{
			return true;
		});
		if(is_writable($this->url))
		{
			unlink($this->url);
		}
		restore_error_handler();
		
		$this->mustRotate = false;
	}
}