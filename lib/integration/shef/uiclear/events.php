<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Shef\UiClear;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Shef\Options\TraitList;
use Shef\Problems\Logger;
use Shef\Problems\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Class Events
 * @package Shef\UiClear\Tools\Logs
 *
 * Ссылки на логи
 *
 * @see \Shef\Problems\Logger
 */
class Events
{
	use TraitList\Events;
	use TraitList\EventResponse;

	protected static function getModuleId(): string
	{
		return Constants::MODULE_ID;
	}

	public static function onBitrixMenuExtInitTopPanelUserMenu(Event $event): EventResult
	{
		return static::returnMainEventSuccess(
			(new Result())->setData([
				'items' => [
					[
						'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS'),
						'ITEMS' => static::getItemsLog(),
						'IS_FOR_ADMIN' => true
					],
					[
						'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_CEVENTS_LOG'),
						'ITEMS' => static::getItemsCEvents(),
						'IS_FOR_ADMIN' => true
					]
				]
			]),
			__FUNCTION__
		);
	}
	
	protected static function getItemsLog(): array
	{
		return array_merge(
			array_merge(...array_map(
				function(Logger $logger)
				{
					$result = [];
					
					if($logger === Logger::Problems)
					{
						foreach(Constants::getAuditTypeList() as $auditType)
						{
							$auditTypeName = ucwords(mb_strtolower($auditType));
							
							$result[] = [
								'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS_FACTORY', [
									'#LOGGER_NAME#' => $auditTypeName
								]),
								'ONCLICK' => 'window.open(\''.Constants::getLogFullPath(mb_strtolower($auditType), false).'\', \'_blank\');',
							];
						}
					}
					else
					{
						$result[] = [
							'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS_FACTORY', [
								'#LOGGER_NAME#' => $logger->name
							]),
							'ONCLICK' => 'window.open(\''.Constants::getLogFullPath(mb_strtolower($logger->name), false).'\', \'_blank\');',
						];
					}
					
					return $result;
				},
				Logger::getEnumUsedInterface(Constants::HandlerTypeFile)
			)),
			[
				[
					'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS_EXCEPTIONS'),
					'ONCLICK' => 'window.open(\''.Manager::getUrlExceptions().'\', \'_blank\');',
				],
				[
					'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS_EMAIL'),
					'ONCLICK' => 'window.open(\''.Manager::getUrlEmail().'\', \'_blank\');',
				],
				[
					'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_LOGS_LIST'),
					'ONCLICK' => 'window.open(\''.Manager::getUrlList().'\', \'_blank\');',
				],
			],
		);
	}
	
	protected static function getItemsCEvents(): array
	{
		return array_merge(
			array_map(
				function(string $auditType)
				{
					$auditTypeName = ucwords(mb_strtolower(str_replace(
						'SH_PROBLEMS_',
						'',
						$auditType
					)));
					return [
						'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_CEVENTS_LOG_FACTORY', [
							'#LOGGER_NAME#' => $auditTypeName
						]),
						'ONCLICK' => 'window.open(\''.Manager::getUrlCEventsLog($auditType).'\', \'_blank\');',
					];
				},
				Constants::getAuditTypeList()
			),
			[
				[
					'TITLE' => Loc::getMessage('SH_PROBLEMS_TOOLS_TBL_SALE_PAY_SYSTEM_ERR_LOG'),
					'ONCLICK' => 'window.open(\''.Manager::getUrlSalePaymentErrorLog().'\', \'_blank\');',
				],
			]
		);
	}
}