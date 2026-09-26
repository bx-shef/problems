<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use Bitrix\Main\Type\Contract;
use InvalidArgumentException;

class ArrayableStrategy
	implements IStrategy
{
	private function checkMessage(mixed $message): void
	{
		if($message instanceof Contract\Arrayable)
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
		/** @var Contract\Arrayable $message */
		$this->checkMessage($message);
		return 'Arrayable';
	}
	
	public function doContext(mixed $message, array $context = []): array
	{
		/** @var Contract\Arrayable $message */
		$this->checkMessage($message);
		
		$context['_message'] = $message->toArray();
		
		return $context;
	}
}