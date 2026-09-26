<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use InvalidArgumentException;

class ArrayStrategy
	implements IStrategy
{
	private function checkMessage(mixed $message): void
	{
		if(is_array($message))
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
		/** @var array $message */
		$this->checkMessage($message);
		return 'Array';
	}
	
	public function doContext(mixed $message, array $context = []): array
	{
		/** @var array $message */
		$this->checkMessage($message);
		
		$context['_message'] = $message;
		
		return $context;
	}
}