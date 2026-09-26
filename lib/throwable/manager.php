<?php
declare(strict_types=1);

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
						$throwable->getTraceAsString()
					),
					true
				);
		}
		
		return new Error(implode(PHP_EOL, $info), $code, $customData);
	}
}