<?php declare(strict_types=1);

namespace Shef\Problems\Throwable\TraceRow\Formatter;

use Shef\Problems\Throwable;

class Full
	implements IFormatter
{
	public function getLine(Throwable\TraceRow\Row $trace, ?int $index = null): string
	{
		$prefix = '';
		
		if(null !== $index)
		{
			$prefix = '#'.$index.' ';
		}
		
		return $prefix.sprintf(
			'%s%s: %s%s%s()',
			$trace->file,
			$trace->line ? ' [line:'.$trace->line.']' : '',
			$trace->class,
			$trace->type,
			$trace->function,
		);
	}
}

