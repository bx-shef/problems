<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Main;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Shef\Problems\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Обработчик событий
 * * onPageStart - Автоподключение css модуля для админки
 * * onEventLogGetAuditTypes - Регистрация типов событий для \CEventLog
 */
class Events
{
	protected static function getModuleId(): string
	{
		return Constants::MODULE_ID;
	}
	
	/**
	 * Автоподключение css модуля для админки
	 *
	 * @throws LoaderException
	 */
	public static function onPageStart(): void
	{
		// В CLI и в агентах контекста запроса может не быть.
		$request = Context::getCurrent()?->getRequest();
		if($request?->isAdminSection() === true)
		{
			Extension::load(Constants::getExtensionList());
		}
	}
	
	/**
	 * Регистрация типов событий для \CEventLog
	 *
	 * @return array
	 * @see \CEventLog::GetEventTypes
	 */
	public static function onEventLogGetAuditTypes(): array
	{
		return array_merge(
			...array_map(
			function (string $code)
			{
				return [
					$code => Loc::getMessage('SH_PROBLEMS_AUDIT_TYPE_'.$code)
				];
			},
			Constants::getAuditTypeList()
		));
	}
}