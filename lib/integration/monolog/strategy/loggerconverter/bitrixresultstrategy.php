<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use InvalidArgumentException;

class BitrixResultStrategy
	implements IStrategy
{
	public const ContextKey = 'BitrixResult';
	public const MessageSuccess = 'Result::Success';
	public const MessageError = 'Result::Error';
	
	private function checkMessage(mixed $message): void
	{
		if($message instanceof Result)
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
		/** @var Result $message */
		$this->checkMessage($message);
		
		if(!$message->isSuccess())
		{
			/** @var Error $firstError */
			$message->getErrorCollection()->rewind();
			$firstError = $message->getErrorCollection()->current();
			
			return sprintf(
				'[%s: %s] %s [code: %s]',
				static::MessageError,
				$message->getErrorCollection()->count(),
				(string) $firstError?->getMessage(),
				(string) $firstError?->getCode(),
			);
		}
		
		return sprintf('[%s]', static::MessageSuccess);
	}
	
	public function doContext(mixed $message, array $context = []): array
	{
		/** @var Result $message */
		$this->checkMessage($message);
		
		if(!isset($context[static::ContextKey]))
		{
			$context[static::ContextKey] = [];
		}
		
		$data = [
			'status' => static::MessageSuccess,
			'data' => $message->getData()
		];
		
		if(!$message->isSuccess())
		{
			$data['status'] = static::MessageError;
			$data['error'] = array_map(function(Error $error){
				return $error->jsonSerialize();
			}, $message->getErrors());
		}
		
		$context[static::ContextKey][] = $data;
		
		unset($data);
		
		return $context;
	}
}