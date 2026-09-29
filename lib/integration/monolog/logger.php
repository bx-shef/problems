<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog;

use Bitrix\Main\Result as BitrixResult;
use Bitrix\Main\Error as BitrixError;
use Bitrix\Main\Type\Contract;

use InvalidArgumentException;
use JsonSerializable;
use Shef\Problems\Integration\Monolog\Strategy\LoggerConverter;
use Stringable;
use Throwable;

class Logger
	extends \Monolog\Logger
{
	/**
	 * Сбой записи не роняет вызывающий код.
	 *
	 * Monolog без обработчика исключений пробрасывает сбой обработчика
	 * наружу: каталог логов вне open_basedir или без прав — и
	 * UnexpectedValueException падает в агент, обмен, обработчик события.
	 * Логгер — не то место, из-за которого должна сорваться бизнес-операция,
	 * поэтому сбой уходит в лог PHP. Свой обработчик — setExceptionHandler().
	 */
	public function __construct(
		string $name,
		array $handlers = [],
		array $processors = [],
		null|\DateTimeZone $timezone = null,
		null|\Psr\Clock\ClockInterface $clock = null
	)
	{
		parent::__construct($name, $handlers, $processors, $timezone, $clock);
		
		$this->setExceptionHandler(static function(Throwable $throwable) use ($name): void
		{
			error_log(sprintf(
				'shef.problems: запись логгера %s не прошла: %s: %s',
				$name,
				$throwable::class,
				$throwable->getMessage()
			));
		});
	}
	
	protected function getStrategyConverterByMessage(mixed $message): LoggerConverter\IStrategy
	{
		if($message instanceof Throwable)
		{
			return new Strategy\LoggerConverter\ThrowableStrategy();
		}
		elseif($message instanceof BitrixError)
		{
			return new Strategy\LoggerConverter\BitrixErrorStrategy();
		}
		elseif($message instanceof BitrixResult)
		{
			return new Strategy\LoggerConverter\BitrixResultStrategy();
		}
		elseif($message instanceof Contract\Arrayable)
		{
			return new Strategy\LoggerConverter\ArrayableStrategy();
		}
		elseif(is_array($message))
		{
			return new Strategy\LoggerConverter\ArrayStrategy();
		}
		elseif($message instanceof Contract\Jsonable)
		{
			return new Strategy\LoggerConverter\JsonableStrategy();
		}
		elseif($message instanceof JsonSerializable)
		{
			return new Strategy\LoggerConverter\JsonSerializableStrategy();
		}
		elseif(
			$message instanceof Stringable
			|| is_string($message)
		)
		{
			return new Strategy\LoggerConverter\StringableStrategy();
		}
		
		throw new InvalidArgumentException(sprintf(
			'$message has wrong type %s',
			gettype($message)
		));
	}
	
	public function log(
		mixed $level,
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::log(
			$level,
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function debug(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::debug(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function info(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::info(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function notice(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::notice(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function warning(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::warning(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function error(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::error(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function critical(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::critical(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function alert(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::alert(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}

	public function emergency(
		mixed $message,
		array $context = []
	): void
	{
		$strategy = $this->getStrategyConverterByMessage($message);
		parent::emergency(
			$strategy->doMessage($message),
			$strategy->doContext($message, $context)
		);
	}
}