<?php

declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

interface IStrategy
{
    public function doMessage(mixed $message): string;

    public function doContext(mixed $message, array $context = []): array;
}
