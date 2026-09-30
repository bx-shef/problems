<?php

declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use InvalidArgumentException;
use JsonSerializable;

class JsonSerializableStrategy implements IStrategy
{
    private function checkMessage(mixed $message): void
    {
        if ($message instanceof JsonSerializable) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            '$message has wrong type %s',
            gettype($message)
        ));
    }

    public function doMessage(mixed $message): string
    {
        /** @var JsonSerializable $message */
        $this->checkMessage($message);
        return 'JsonSerializable';
    }

    public function doContext(mixed $message, array $context = []): array
    {
        /** @var JsonSerializable $message */
        $this->checkMessage($message);

        $context['_message'] = $message->jsonSerialize();

        return $context;
    }
}
