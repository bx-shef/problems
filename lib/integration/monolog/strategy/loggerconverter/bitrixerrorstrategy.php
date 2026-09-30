<?php

declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use Bitrix\Main\Error;
use InvalidArgumentException;

class BitrixErrorStrategy implements IStrategy
{
    public const ContextKey = 'BitrixError';

    private function checkMessage(mixed $message): void
    {
        if ($message instanceof Error) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '$message has wrong type %s',
            gettype($message)
        ));
    }

    public function doMessage(mixed $message): string
    {
        /** @var Error $message */
        $this->checkMessage($message);

        return sprintf(
            '%s [code: %s]',
            $message->getMessage(),
            $message->getCode()
        );
    }

    public function doContext(mixed $message, array $context = []): array
    {
        /** @var Error $message */
        $this->checkMessage($message);

        if (!isset($context[static::ContextKey])) {
            $context[static::ContextKey] = [];
        }

        $context[static::ContextKey][] = $message->jsonSerialize();

        return $context;
    }
}
