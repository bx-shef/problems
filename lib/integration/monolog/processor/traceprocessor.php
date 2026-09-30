<?php

declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Processor;

use Exception;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Shef\Problems\Throwable;
use Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

class TraceProcessor implements ProcessorInterface
{
    public const ModeArray = 1;
    public const ModeLine = 2;
    public const ModeBr = 3;
    public const ModeNewLine = 4;

    private array $traceList = [];
    private ?Throwable\TraceRow\Formatter\IFormatter $traceFormatter = null;

    public function __construct(
        public readonly bool $includeStackTraces = true,
        public readonly int $mode = TraceProcessor::ModeArray
    ) {
        $this->initTraceFormatter();
    }

    protected function initTraceFormatter(): void
    {
        if ($this->includeStackTraces === false) {
            $this->traceFormatter = new Throwable\TraceRow\Formatter\Simple();
        } else {
            $this->traceFormatter = new Throwable\TraceRow\Formatter\Full();
        }
    }

    protected function initStackTrace(LogRecord $record): void
    {
        $this->traceList = [];

        if (
            isset($record->context[LoggerConverter\ThrowableStrategy::ContextKey])
            && $record->context[LoggerConverter\ThrowableStrategy::ContextKey] instanceof \Throwable
        ) {
            $this->traceList = $record->context[LoggerConverter\ThrowableStrategy::ContextKey]->getTrace();
        } else {
            $this->traceList = static::skipLoggerFrames((new Exception())->getTrace());
        }

        $this->traceList = array_map(function (array $trace) {
            return new Throwable\TraceRow\Row($trace);
        }, $this->traceList);

        $index = 0;
        $this->traceList = array_map(
            function (Throwable\TraceRow\Row $trace) use (&$index): string {
                return $this->traceFormatter?->getLine($trace, $index++);
            },
            $this->traceList
        );
        unset($index);
    }

    /**
     * Отрезает кадры самого логгера: первым остаётся тот, кто позвал логгер.
     *
     * Раньше здесь стояло array_slice(..., 6) — глубина вызова от точки
     * «$logger->debug()» до процессора, повешенного на ОБРАБОТЧИК. Повесь
     * процессор на логгер (pushProcessor у Logger), позови через log() или
     * через обёртку — глубина другая, и трассировка начиналась бы то с
     * внутренностей Monolog, то с середины вызывающего кода.
     *
     * Поэтому режем по месту вызова, а не по счёту: пропускаем кадры, вызванные
     * из файлов Monolog и из слоя Integration\Monolog модуля. Каталог Monolog
     * берётся у настоящего класса — он разный для Composer и для своей копии.
     *
     * @param array[] $trace
     * @return array[]
     */
    protected static function skipLoggerFrames(array $trace): array
    {
        $dirs = array_map(
            static fn (string $dir): string => str_replace('\\', '/', $dir).'/',
            [
                dirname((string)(new \ReflectionClass(\Monolog\Logger::class))->getFileName()),
                dirname(__DIR__),
            ]
        );

        $isLoggerFrame = static function (array $frame) use ($dirs): bool {
            $file = str_replace('\\', '/', (string)($frame['file'] ?? ''));
            if ($file === '') {
                return false;
            }

            foreach ($dirs as $dir) {
                if (str_starts_with($file, $dir)) {
                    return true;
                }
            }

            return false;
        };

        $index = 0;
        $count = count($trace);
        while ($index < $count && $isLoggerFrame($trace[$index])) {
            $index++;
        }

        return array_values(array_slice($trace, $index));
    }

    protected function makeStackTraces(): array
    {
        if ($this->includeStackTraces === false) {
            $list = array_slice($this->traceList, 0, 1);
        } else {
            $list = $this->traceList;
        }

        return $list;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $this->initStackTrace($record);

        $record->extra['trace'] = $this->makeStackTraces();
        $record->extra['trace'] = match($this->mode) {
            static::ModeLine => join('; ', $record->extra['trace']),
            static::ModeNewLine => join(PHP_EOL, $record->extra['trace']),
            static::ModeBr => join(PHP_EOL.'<br>', $record->extra['trace']),
            default => $record->extra['trace'],
        };

        return $record;
    }
}
