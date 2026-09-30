<?php

declare(strict_types=1);

namespace Shef\Problems\Factory\Trait;

use Psr\Log\LoggerInterface;
use Monolog\Level;
use Bitrix\Main\DI;
use Shef\Problems\Main\Constants;

/**
 * Трейт для работы с системным логом
 */
trait LoggerProblems
{
    /** @var LoggerInterface для логирования поблем */
    protected LoggerInterface $logger;

    /**
     * Класс где происходит событие
     * @return string
     */
    abstract public static function getClassName(): string;

    /**
     * Код модуля
     * @return string
     */
    abstract public static function getModuleId(): string;

    /**
     * Кто ответственный
     * @return int
     */
    public static function getAssignedId(): int
    {
        return Constants::getDefUserId();
    }

    /**
     * Уровень логирования
     * @return Level
     */
    protected static function getLogLevel(): \Monolog\Level
    {
        return \Monolog\Level::Info;
    }

    /**
     * Тип события
     * @return string
     */
    protected static function getAuditType(): string
    {
        return Constants::getDefAuditType();
    }

    /**
     * Созданние логгера
     *
     * @return LoggerInterface
     * @throws \Bitrix\Main\ObjectNotFoundException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected static function createLogger(): LoggerInterface
    {
        /** @var \Shef\Problems\Factory\SystemLoggerFactory $factory */
        $factory = DI\ServiceLocator::getInstance()
            ->get('shef.problems.factory.system.logger');

        return $factory::build(
            logLevel: static::getLogLevel(),
            auditType: static::getAuditType(),
            moduleId: static::getModuleId(),
            className: static::getClassName(),
            assigned: static::getAssignedId(),
        );
    }

    /**
     * Инициализация логгера
     *
     * @return void
     * @throws \Bitrix\Main\ObjectNotFoundException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initLogger(): void
    {
        $this->logger = static::createLogger();
    }

    /**
     * Ручная установка логгера
     * @param LoggerInterface $logger
     * @return $this
     */
    public function configureLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }
}
