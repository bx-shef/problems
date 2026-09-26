<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use InvalidArgumentException;
use Throwable;

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
		
		// get_debug_type, а не Utils::getAllParents(): тот принимает только
		// объект, и на строке вместо внятного InvalidArgumentException
		// вылетал бы TypeError из самой проверки.
		throw new InvalidArgumentException(sprintf(
			'$message has wrong type %s',
			get_debug_type($message)
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