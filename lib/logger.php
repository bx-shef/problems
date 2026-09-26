<?php declare(strict_types=1);

namespace Shef\Problems;

use InvalidArgumentException;
use LogicException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Bitrix\Main\DI;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Config;
use Shef\Problems\Main\Constants;

/**
 * Предустановленные логгеры модуля.
 *
 * Каждый случай — сервис из .settings.php (ключ services); сверяет
 * tests/settings_test.php. Logger::Problems — не логгер, а фабрика: его
 * строят через трейт Factory\Trait\LoggerProblems.
 */
enum Logger
{
	case Pr;
	case PrHtml;
	case Log1;
	case Log;
	case Problems;
	case Deprecations;
	
	// region filter by interface ////
	public static function getEnumUsedInterface(string $handlerType): array
	{
		if(!in_array($handlerType, Constants::getHandlerTypeList()))
		{
			throw new InvalidArgumentException(sprintf(
				'handlerType %s not support',
				$handlerType
			));
		}
		
		$services = Config\Configuration::getInstance(Constants::getModuleId())->get('services');
		if(!is_array($services))
		{
			return [];
		}
		
		return array_values(array_filter(
			self::cases(),
			function(Logger $logger)
			use ($handlerType, $services)
			{
				$service = $services[$logger->getServiceName()] ?? [];
				if(empty($service['handlerType']) || !is_array($service['handlerType']))
				{
					return false;
				}
				
				return in_array($handlerType, $service['handlerType'], true);
			}
		));
	}
	
	public function getServiceName(): string
	{
		return match($this)
		{
			Logger::Pr => 'shef.problems.pr.debug',
			Logger::PrHtml => 'shef.problems.prHtml.debug',
			Logger::Log1 => 'shef.problems.log1.debug',
			Logger::Log => 'shef.problems.log.debug',
			Logger::Problems => 'shef.problems.factory.system.logger',
			Logger::Deprecations => 'shef.problems.deprecations.alert',
		};
	}
	// endregion ////
	
	
	/**
	 * Возвращает логгер
	 *
	 * @return LoggerInterface
	 *
	 * @throws ObjectNotFoundException
	 * @throws NotFoundExceptionInterface
	 * @memo для Logger\Problems нужна своя функция
	 */
	public function getLogger(): LoggerInterface
	{
		if($this === Logger::Problems)
		{
			throw new LogicException('For Logger::Problems need use self function. See Shef\Problems\Factory\Trait\LoggerProblems');
		}
		
		$serviceLocator = DI\ServiceLocator::getInstance();
		$serviceName = $this->getServiceName();
		
		if(!$serviceLocator->has($serviceName))
		{
			throw new LogicException(sprintf(
				'For %s not find service locator',
				$this->name
			));
		}
		
		return $serviceLocator->get($serviceName);
	}
}