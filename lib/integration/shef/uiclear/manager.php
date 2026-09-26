<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Shef\UiClear;

use Bitrix\Main\Application;
use Shef\Problems\Main\Constants;

/**
 * Ссылки на логи
 *
 * @see \Shef\Problems\Logger
 */
class Manager
{
	/**
	 * Возвращает ссылку на список логов в админке
	 * @return string
	 */
	public static function getUrlList(): string
	{
		$context = Application::getInstance()->getContext();
		return sprintf(
			'/bitrix/admin/fileman_admin.php?lang=%s&site=%s&path=%s',
			$context->getLanguage(),
			$context->getSite(),
			Constants::getLogPath()
		);
	}

	/**
	 * Возвращает ссылку на /local/sh_log/exceptions.log
	 *
	 * @see: /bitrix/.settings.php -> exception_handling
	 *
	 * @return string
	 */
	public static function getUrlExceptions(): string
	{
		return Constants::getLogFullPath('exceptions', false);
	}

	/**
	 * Возвращает ссылку на /local/sh_log/mailer.log
	 *
	 * @see: /bitrix/.settings.php -> smtp
	 *
	 * @return string
	 */
	public static function getUrlEmail(): string
	{
		return Constants::getLogFullPath('mailer', false);
	}
	
	/**
	 * Возвращает ссылку на журнал событий в админке
	 * @param string $auditType
	 * @return string
	 */
	public static function getUrlCEventsLog(string $auditType): string
	{
		return sprintf(
			'/bitrix/admin/event_log.php?lang=%s&set_filter=Y&adm_filter_applied=0&find_type=audit_type_id&find_audit_type[]=%s',
			Application::getInstance()->getContext()->getLanguage(),
			$auditType
		);
	}
	
	/**
	 * Возвращает ссылку на таблицу проблем платежных систем в админке
	 * @return string
	 */
	public static function getUrlSalePaymentErrorLog(): string
	{
		return sprintf(
			'/bitrix/admin/perfmon_table.php?lang=%s&table_name=%s',
			Application::getInstance()->getContext()->getLanguage(),
			'b_sale_pay_system_err_log'
		);
	}
}