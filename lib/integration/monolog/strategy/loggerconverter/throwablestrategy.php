<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use InvalidArgumentException;
use Throwable;
use Shef\Problems\Main\Utils;

class ThrowableStrategy
	implements IStrategy
{
	public const ContextKey = 'throwable';
	
	private function checkMessage(mixed $message): void
	{
		if($message instanceof Throwable)
		{
			return;
		}
		
		throw new InvalidArgumentException(sprintf(
			'$message has wrong type %s: %s',
			gettype($message),
			Utils::getAllParents($message)
		));
	}
	
	public function doMessage(mixed $message): string
	{
		/** @var Throwable $message */
		$this->checkMessage($message);
		return $message->getMessage();
	}
	
	public function doContext(mixed $message, array $context = []): array
	{
		/** @var Throwable $message */
		$this->checkMessage($message);
		
		$context[static::ContextKey] = $message;
		
		return $context;
	}
}