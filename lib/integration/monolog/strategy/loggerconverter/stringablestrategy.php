<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use InvalidArgumentException;
use Stringable;

class StringableStrategy
	implements IStrategy
{
	private function checkMessage(mixed $message): void
	{
		if(
			$message instanceof Stringable
			|| is_string($message)
		)
		{
			return;
		}
		
		throw new InvalidArgumentException(sprintf(
			'$message has wrong type %s',
			gettype($message)
		));
	}
	
	public function doMessage(mixed $message): string
	{
		/** @var string|Stringable $message */
		$this->checkMessage($message);
		return (string) $message;
	}
	
	public function doContext(mixed $message, array $context = []): array
	{
		/** @var string|Stringable $message */
		$this->checkMessage($message);
		
		return $context;
	}
}