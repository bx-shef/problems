<?php

declare(strict_types=1);

/**
 * Логгер, которому можно отдать не только строку.
 *
 * ЦЕЛЬ
 *   Показать, во что \Shef\Problems\Integration\Monolog\Logger превращает
 *   исключение, Result и Error ядра, массив: что становится сообщением, а что
 *   уходит в контекст. Это контракт, на который опираетесь вы, когда пишете
 *   $logger->error($result) вместо того, чтобы собирать строку руками.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Везде, где есть Monolog-логгер модуля: сервисы shef.problems.*, свой
 *   логгер через \Bitrix\Main\Diag\Logger::create(), фабрика проблем.
 *   Особенно — после вызова D7-метода: Result с ошибками пишется одной
 *   строкой, и все ошибки остаются в контексте.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: logger», код возврата 0.
 *   По сути:
 *     * исключение: сообщение — его текст, само исключение — в контексте
 *       (из него TraceProcessor возьмёт трассировку);
 *     * Result: сообщение — первая ошибка и их число, все ошибки и данные —
 *       в контексте;
 *     * массив: сообщение «Array», массив — в контексте под _message;
 *     * число или объект без контракта — InvalidArgumentException, а не
 *       молча записанная пустота.
 *
 * ЗАПУСК
 *   php examples/logger.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/logger.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: записи уходят в TestHandler Monolog — в память, не в файл и не в
 *   журнал.
 */

require_once __DIR__.'/_bootstrap.php';

title('Логгер модуля: что можно передать первым аргументом');

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Logger;

$memory = new TestHandler(Level::Debug);
$logger = (new Logger('example'))->pushHandler($memory);

/** Последняя запись: [сообщение, контекст]. */
$last = static function () use ($memory): array {
    $records = $memory->getRecords();
    $record = end($records);

    return [$record->message, $record->context];
};

step('Исключение');

try {
    throw new RuntimeException('Не удалось прочитать файл обмена');
} catch (RuntimeException $exception) {
    $logger->error($exception, ['file' => 'import.xml']);
}

[$message, $context] = $last();
check('сообщение — текст исключения', $message, 'Не удалось прочитать файл обмена');
check('исключение — в контексте', ($context['throwable'] ?? null) instanceof RuntimeException, true);
check('свой контекст на месте', $context['file'] ?? null, 'import.xml');

step('Result ядра с ошибками');

$result = new Result();
$result->addError(new Error('Не указан склад', 'NO_STORE'));
$result->addError(new Error('Не указана цена', 'NO_PRICE'));

$logger->warning($result);

[$message, $context] = $last();
check('сообщение — первая ошибка и их число', $message, '[Result::Error: 2] Не указан склад [code: NO_STORE]');
check('все ошибки — в контексте', count($context['BitrixResult'][0]['error'] ?? []), 2);

step('Успешный Result');

$logger->info((new Result())->setData(['orderId' => 77]));

[$message, $context] = $last();
check('сообщение', $message, '[Result::Success]');
check('данные — в контексте', $context['BitrixResult'][0]['data'] ?? null, ['orderId' => 77]);

step('Массив');

$logger->debug(['step' => 'prices', 'rows' => 120]);

check('сообщение и контекст', $last(), ['Array', ['_message' => ['step' => 'prices', 'rows' => 120]]]);

step('То, что превратить нельзя');

$thrown = null;
try {
    $logger->info(42);
} catch (InvalidArgumentException $exception) {
    $thrown = $exception::class;
}

check('число — InvalidArgumentException', $thrown, InvalidArgumentException::class);
note('Такую запись лучше не потерять молча: ошибка видна сразу, в том же месте.');

done('logger');
