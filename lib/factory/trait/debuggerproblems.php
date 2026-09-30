<?php

declare(strict_types=1);

namespace Shef\Problems\Factory\Trait;

use Psr\Log\LoggerInterface;
use Bitrix\Main\DI;

/**
 * Трейт для работы с логом отладки во время написания кода
 */
trait DebuggerProblems
{
    /** @var LoggerInterface для отладки во время написания кода */
    protected LoggerInterface $debugger;

    /**
     * Создание дебагера
     *
     * @return LoggerInterface
     * @throws \Bitrix\Main\ObjectNotFoundException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected static function createDebugger(): LoggerInterface
    {
        return DI\ServiceLocator::getInstance()->get('shef.problems.prHtml.debug');
    }

    /**
     * Инициализация дебагера
     *
     * @return void
     * @throws \Bitrix\Main\ObjectNotFoundException
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initDebugger(): void
    {
        $this->debugger = static::createDebugger();
    }

    /**
     * Ручная установка логгера
     * @param LoggerInterface $debugger
     * @return $this
     */
    public function configureDebugger(LoggerInterface $debugger): static
    {
        $this->debugger = $debugger;
        return $this;
    }
}
