<?php declare(strict_types=1);

/**
 * Лог одной цепочки: Log1Handler.
 *
 * ЦЕЛЬ
 *   Показать, чем Log1Handler отличается от обычного StreamHandler: первая
 *   запись за жизнь обработчика стирает файл, остальные дописывают. В файле
 *   остаётся ровно последний прогон — без истории прошлых запросов.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Разбор одного сценария: «что происходит при сохранении сделки», «что
 *   приходит в обработчик». Открыл страницу — в логе только этот запрос.
 *   Сервис shef.problems.log1.debug и logger Logger::Log1 устроены так же.
 *   Для постоянного лога не годится: предыдущий прогон стирается.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: log1», код возврата 0.
 *   По сути:
 *     * старое содержимое файла после первой записи исчезло;
 *     * записи одного обработчика идут одна за другой;
 *     * новый обработчик (новый запрос) снова начинает с чистого файла.
 *
 * ЗАПУСК
 *   php examples/log1.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/log1.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: файл лежит во временном каталоге системы, а не в каталоге логов
 *   портала, и пример его удаляет.
 */

require_once __DIR__.'/_bootstrap.php';

title('Лог одной цепочки: Log1Handler');

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Shef\Problems\Integration\Monolog\Handler\Log1Handler;
use Shef\Problems\Integration\Monolog\Logger;

$file = sys_get_temp_dir().'/shef-problems-example-log1-'.getmypid().'.log';
file_put_contents($file, 'запись прошлого запроса'.PHP_EOL);

/** Логгер одного «запроса». */
$request = static function() use ($file): Logger
{
	return (new Logger('log1'))->pushHandler(
		(new Log1Handler(filename: $file, level: Level::Debug))
			->setFormatter(new LineFormatter('%message%'.PHP_EOL))
	);
};

step('Первый запрос');

$logger = $request();
$logger->debug('шаг 1');
$logger->debug('шаг 2');

check('прошлого запроса в файле нет', str_contains((string)file_get_contents($file), 'прошлого'), false);
check('в файле — этот запрос целиком', (string)file_get_contents($file), 'шаг 1'.PHP_EOL.'шаг 2'.PHP_EOL);

step('Второй запрос');

$logger = $request();
$logger->debug('новый шаг');

check('файл начат заново', (string)file_get_contents($file), 'новый шаг'.PHP_EOL);

unlink($file);

done('log1');
