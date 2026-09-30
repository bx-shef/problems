<?php

declare(strict_types=1);

/**
 * Исключение -> ошибка ядра: Throwable\Manager::buildError().
 *
 * ЦЕЛЬ
 *   Показать, как исключение превращается в \Bitrix\Main\Error, чтобы
 *   вернуть его в Result, а не бросать дальше: текст, файл и строка — в
 *   сообщении, трассировка — по желанию, корень сайта из путей срезан.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Методы, которые по канону возвращают Result: сервисы, ajax-действия,
 *   компоненты. Поймали \Throwable — положили в Result, вызывающий решает,
 *   показать ли его и записать ли в лог.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: throwable», код возврата 0.
 *   По сути:
 *     * в сообщении ошибки — текст, файл, строка;
 *     * isUseTrace: false — без трассировки, true — с ней;
 *     * код и customData ошибки — те, что передали;
 *     * корня сайта в путях нет.
 *
 * ЗАПУСК
 *   php examples/throwable.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/throwable.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: пример работает только с памятью процесса.
 */

require_once __DIR__.'/_bootstrap.php';

title('Исключение в ошибку ядра');

use Bitrix\Main\Application;
use Bitrix\Main\Result;
use Shef\Problems\Throwable\Manager;

$fail = static function (): never {
    throw new DomainException('Сумма заказа отрицательная');
};

step('Без трассировки');

$result = new Result();

try {
    $fail();
} catch (\Throwable $throwable) {
    $result->addError(Manager::buildError(
        throwable: $throwable,
        isUseTrace: false,
        code: 'NEGATIVE_SUM',
        customData: ['orderId' => 15]
    ));
}

$error = $result->getErrors()[0];
$lines = explode(PHP_EOL, $error->getMessage());

check('Result неуспешный', $result->isSuccess(), false);
check('первая строка — текст', $lines[0], 'Throwable: Сумма заказа отрицательная');
check('вторая — файл', str_starts_with($lines[1], 'File: '), true);
check('третья — строка', str_starts_with($lines[2], 'Line: '), true);
check('трассировки нет', count($lines), 3);
check('код ошибки', $error->getCode(), 'NEGATIVE_SUM');
check('customData', $error->getCustomData(), ['orderId' => 15]);

step('С трассировкой');

try {
    $fail();
} catch (\Throwable $throwable) {
    $error = Manager::buildError($throwable);
}

check('трассировка на месте', str_contains($error->getMessage(), 'Trace: #0'), true);

$documentRoot = Application::getDocumentRoot();
check(
    'корня сайта в трассировке нет',
    '' === $documentRoot || !str_contains(mb_substr($error->getMessage(), mb_strpos($error->getMessage(), 'Trace:')), $documentRoot.'/'),
    true
);
note('Файл в строке «File:» остаётся полным: так его выдаёт само исключение.');

done('throwable');
