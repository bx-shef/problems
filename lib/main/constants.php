<?php declare(strict_types=1);

namespace Shef\Problems\Main;

use Monolog\Formatter;
use Bitrix\Main\Config;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Application;

class Constants
{
	public const MODULE_ID = 'shef.problems';
	
	public const EmptyValue = 'empty';
	
	public const AuditTypeProblem = 'SH_PROBLEMS_PROBLEM';
	public const AuditTypeSync = 'SH_PROBLEMS_SYNC';
	public const AuditTypeProduct = 'SH_PROBLEMS_PRODUCT';
	public const AuditTypeSale = 'SH_PROBLEMS_SALE';
	
	public const HandlerTypePrint = 'print';
	public const HandlerTypeFile = 'file';
	public const HandlerTypeBitrixEventLog = 'bitrix.event.log';
	
	public static function getModuleId(): string
	{
		return static::MODULE_ID;
	}
	
	public static function getAuditTypeList(): array
	{
		return [
			static::AuditTypeProblem,
			static::AuditTypeSync,
			static::AuditTypeProduct,
			static::AuditTypeSale,
		];
	}
	
	public static function getDefAuditType(): string
	{
		return static::AuditTypeProblem;
	}
	
	public static function getHandlerTypeList(): array
	{
		return [
			static::HandlerTypePrint,
			static::HandlerTypeFile,
			static::HandlerTypeBitrixEventLog,
		];
	}
	
	public static function getSettingsOptions(): array 
	{
		$list = Config\Configuration::getInstance(static::getModuleId())
			->get('options');
		
		if(!is_array($list))
		{
			$list = [];
		}
		
		return $list;
	}
	
	/**
	 * Папка для хранения логов
	 * @return string
	 */
	public static function getLogPath(): string
	{
		return '/local/sh_log';
	}
	
	public static function getLogFullPath(string $name, bool $isAbsolute = true): string
	{
		return sprintf(
			'%s%s/%s.log',
			$isAbsolute ? Application::getDocumentRoot() : '',
			static::getLogPath(),
			$name
		);
	}
	
	public static function getDefaultFormatter(): Formatter\FormatterInterface
	{
		return (
			new Formatter\LineFormatter(
				'[%datetime%] %channel%.%level_name%: %message% %context% %extra%'.PHP_EOL,
				'Y-m-d H:i:s',
			)
		)
		->allowInlineLineBreaks()
		->ignoreEmptyContextAndExtra();
	}
	
	// region Users ////
	
	/**
	 * @throws LoaderException
	 */
	public static function getSystemUserId(): int
	{
		if(!Loader::includeModule('shef.options'))
		{
			return 1;
		}
		
		return \Shef\Options\Main\Constants::getSystemUserId();
	}

	public static function getDefUserId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_defuserid', 1);
	}

	public static function getAdminId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_adminid', 1);
	}

	public static function getDirectorId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_dirid', 1);
	}

	public static function getSyncUserId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_syncuserid', 1);
	}

	public static function getProductsUserId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_productsuserid', 1);
	}

	public static function getSaleUserId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_saleuserid', 1);
	}
	// endregion ////

	// region Groups ////
	/**
	 * в какую группу складывать задачи
	 */
	public static function getGroupIdTask(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'GRP_tskid', 1);
	}

	public static function getGroupIdIntegrateB24(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'GRP_intb24id', 1);
	}
	// endregion ////
}