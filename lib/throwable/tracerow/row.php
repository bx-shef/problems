<?php

declare(strict_types=1);

namespace Shef\Problems\Throwable\TraceRow;

use Bitrix\Main\Application;

/**
 * Строка трассировки из debug_backtrace() / Throwable::getTrace().
 *
 * Ключи кадра читаются только через ??: у кадра встроенной функции нет file и
 * line, у вызова функции нет class и type. Прямое чтение давало на PHP 8
 * «Undefined array key» на КАЖДУЮ запись лога с TraceProcessor — не падало,
 * но засоряло лог портала ровно тем, от чего логгер должен избавлять.
 */
class Row
{
    public readonly string $file;
    public readonly ?int $line;
    public readonly string $function;
    public readonly string $class;
    public readonly string $type;

    public function __construct(array $row)
    {
        $file = (string)($row['file'] ?? '');
        $line = (int)($row['line'] ?? 0);

        $this->file = $file !== ''
            ? str_replace(Application::getDocumentRoot(), '', $file)
            : '[internal function]';
        $this->line = $line > 0 ? $line : null;
        $this->function = (string)($row['function'] ?? '');
        $this->class = (string)($row['class'] ?? '');
        $this->type = (string)($row['type'] ?? '');
    }
}
