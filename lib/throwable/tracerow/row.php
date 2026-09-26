<?php declare(strict_types=1);

namespace Shef\Problems\Throwable\TraceRow;

use Bitrix\Main\Application;

class Row
{
	public readonly string $file;
	public readonly ?int $line;
	public readonly string $function;
	public readonly string $class;
	public readonly string $type;
	
	public function __construct(array $row)
	{
		$this->file = $row['file'] ? str_replace(Application::getDocumentRoot(), '',(string)$row['file']) : '[internal function]';
		$this->line = $row['line'] ? (int)$row['line'] : null;
		$this->function = $row['function'] ? (string)$row['function'] : '';
		$this->class = $row['class'] ? (string)$row['class'] : '';
		$this->type = $row['type'] ? (string)$row['type'] : '';
	}
}