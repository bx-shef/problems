<?php

declare(strict_types=1);

namespace Shef\Problems\Throwable\TraceRow\Formatter;

use Shef\Problems\Throwable;

class Simple implements IFormatter
{
    public function getLine(Throwable\TraceRow\Row $trace, ?int $index = null): string
    {
        return sprintf(
            'File: %s%s',
            $trace->file,
            $trace->line ? ' [line: '.$trace->line.']' : '',
        );
    }
}
