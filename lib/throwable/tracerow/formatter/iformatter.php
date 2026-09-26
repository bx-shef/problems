<?php declare(strict_types=1);

namespace Shef\Problems\Throwable\TraceRow\Formatter;

use Shef\Problems\Throwable;

interface IFormatter
{
	public function getLine(Throwable\TraceRow\Row $trace, ?int $index = null): string;
}