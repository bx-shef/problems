<?php declare(strict_types=1);

namespace Shef\Problems\Throwable;

use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Throwable;

/**
 * Обработка ошибок Throwable
 *
 * <code>
 * use Shef\Problems;
 * try
 * {
 *     // ... ////
 * }
 * catch(\Throwable $throwable)
 * {
 *     $this->addError(Problems\Throwable\Manager::buildError($throwable, false, static::ErrorCodeFileFailParse));
 *     return;
 * }
 * </code>
 */
class Manager
{
	public static function buildError(
		Throwable $throwable,
		bool $isUseTrace = true,
		int|string $code = 0,
		mixed $customData = null
	): Error
	{
		$info = [
			'Throwable: '.$throwable->getMessage(),
			'File: '.$throwable->getFile(),
			'Line: '.$throwable->getLine(),
		];
		if($isUseTrace)
		{
			$info[] = 'Trace: '
				.print_r(
					str_replace(
						Application::getDocumentRoot(),
						'',
						static::traceToString($throwable->getTrace())
					),
					true
				);
		}
		
		return new Error(implode(PHP_EOL, $info), $code, $customData);
	}
	
	/**
	 * Трассировка строкой, как getTraceAsString(), но без аргументов.
	 *
	 * При zend.exception_ignore_args=Off (умолчание PHP и php.ini-development)
	 * getTraceAsString() печатает аргументы вызовов — пароль, токен из
	 * login($user, $password) уходил бы в текст ошибки и в лог.
	 *
	 * @param array<int, array<string, mixed>> $trace Throwable::getTrace() или debug_backtrace()
	 */
	public static function traceToString(array $trace): string
	{
		$lines = [];
		foreach(array_values($trace) as $i => $frame)
		{
			$lines[] = sprintf(
				'#%d %s%s%s%s()',
				$i,
				isset($frame['file']) ? $frame['file'].'('.($frame['line'] ?? 0).'): ' : '[internal function]: ',
				(string)($frame['class'] ?? ''),
				(string)($frame['type'] ?? ''),
				(string)($frame['function'] ?? '')
			);
		}
		$lines[] = '#'.count($lines).' {main}';
		
		return implode(PHP_EOL, $lines);
	}
}