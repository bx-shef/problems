<?php

declare(strict_types=1);

/**
 * Utils::workDateAdd(): рабочие дни через калькулятор бизнес-процессов, а
 * сломался он — календарные, но дата всё равно есть.
 *
 * Калькулятор (CBPCalc) — не публичный API. На свежих ядрах он ломался, и в
 * рабочей копии 1.1.8 метод свели к голому $date->add(): рабочие дни
 * перестали считаться нигде, даже там, где калькулятор жив. Здесь держим
 * оба случая.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Type\Date;
use Shef\Problems\Main\Utils;

// region Заглушки bizproc ////
class CBPActivity
{
    public function __construct(public readonly string $name)
    {
    }
}

class CBPCalc
{
    /** @var callable(string): mixed */
    public static $answer;

    public static ?string $lastExpression = null;

    public function __construct(CBPActivity $activity)
    {
    }

    public function Calculate(string $expression): mixed
    {
        static::$lastExpression = $expression;

        return (static::$answer)($expression);
    }
}
// endregion ////

Check::group('калькулятор жив — рабочие дни');

CBPCalc::$answer = static fn (): string => '16.03.2026';
$date = new Date('13.03.2026', 'd.m.Y');
$result = Utils::workDateAdd($date, '1D');

Check::same('дата от калькулятора', $result->value, '16.03.2026');
Check::same('в калькулятор ушло выражение workdateadd', CBPCalc::$lastExpression, '=workdateadd("13.03.2026","1D")');
Check::same('исходная дата не тронута', $date->added, []);

Check::group('калькулятор упал — календарные дни, без исключения');

CBPCalc::$answer = static function (): never {
    throw new Error('Call to undefined method');
};
$date = new Date('13.03.2026', 'd.m.Y');
$result = Utils::workDateAdd($date, '1D');

Check::same('прибавлено как интервал', $result->added, ['1D']);
Check::same('это та же дата — как делает ядро', $result, $date);

Check::group('калькулятор вернул пустоту — тоже календарные');

CBPCalc::$answer = static fn (): string => '';
$date = new Date('13.03.2026', 'd.m.Y');
Check::same('прибавлено как интервал', Utils::workDateAdd($date, '2D')->added, ['2D']);

CBPCalc::$answer = static fn (): mixed => null;
$date = new Date('13.03.2026', 'd.m.Y');
Check::same('null — тоже', Utils::workDateAdd($date, '3D')->added, ['3D']);

Check::finish();
