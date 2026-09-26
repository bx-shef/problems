<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Main;

use Bitrix\Main\Localization\Loc;
use Shef\Problems\Logger;
use Shef\Problems\Main\AdminPage;
use Shef\Problems\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Раздел «Учёт проблем» в меню административной части.
 *
 * Штатная замена пунктов, которые до 2.0.0 модуль вешал на верхнюю панель
 * через событие модуля shef.uiclear. Модуль больше от shef.uiclear не
 * зависит: меню отдаёт admin/menu.php, а его ядро подключает само для
 * каждого установленного модуля — регистрировать ничего не нужно.
 *
 * Логи открываются страницей модуля /bitrix/admin/shef_problems_logs.php:
 * каталог логов лежит вне корня сайта, и ни прямая ссылка, ни файловый
 * менеджер Битрикса до него не дотянутся. @see docs/security.md
 *
 * Метод чистый — без глобалов и обращений к базе, — чтобы его можно было
 * проверить без портала: права и язык передаёт admin/menu.php.
 */
class AdminMenu
{
	public const PARENT_MENU = 'global_menu_settings';
	public const ITEMS_ID = 'menu_shef_problems';

	public const LOGS_PAGE = '/bitrix/admin/'.AdminPage::FILE;
	
	/**
	 * @param string $lang язык административной части
	 * @param bool $isPerfmonInstalled ссылка на таблицу ошибок платёжных
	 *        систем ведёт в perfmon — без модуля она открыла бы ошибку
	 * @return array описание раздела в формате admin/menu.php
	 */
	public static function build(
		string $lang,
		bool $isPerfmonInstalled = false
	): array
	{
		return [
			'parent_menu' => static::PARENT_MENU,
			'section' => Constants::MODULE_ID,
			'sort' => 1900,
			'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU'),
			'title' => (string)Loc::getMessage('SH_PROBLEMS_MENU_TITLE'),
			'icon' => 'sys_menu_icon',
			'items_id' => static::ITEMS_ID,
			'items' => [
				[
					'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_LOGS'),
					'items_id' => static::ITEMS_ID.'_logs',
					'items' => static::getLogItems($lang),
				],
				[
					'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_EVENT_LOG'),
					'items_id' => static::ITEMS_ID.'_event_log',
					'items' => static::getEventLogItems($lang, $isPerfmonInstalled),
				],
				[
					'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_SETTINGS'),
					'url' => static::getUrlSettings($lang),
				],
			],
		];
	}

	/**
	 * Файлы логов: всё, что пишут логгеры с обработчиком «file», плюс
	 * файлы, которые на проекте принято класть туда же.
	 */
	protected static function getLogItems(string $lang): array
	{
		$items = [];

		foreach(Logger::getEnumUsedInterface(Constants::HandlerTypeFile) as $logger)
		{
			// Фабрика пишет в свой файл на каждый тип события.
			if($logger === Logger::Problems)
			{
				foreach(Constants::getAuditTypeList() as $auditType)
				{
					$items[] = static::getLogFileItem(
						ucwords(mb_strtolower($auditType)),
						mb_strtolower($auditType),
						$lang
					);
				}

				continue;
			}

			$items[] = static::getLogFileItem(
				$logger->name,
				mb_strtolower($logger->name),
				$lang
			);
		}

		// exceptions.log — /bitrix/.settings.php, exception_handling;
		// mailer.log — /bitrix/.settings.php, smtp. Путь задаёт проект, модуль
		// только предлагает класть их в каталог логов — тогда они видны здесь.
		$items[] = [
			'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_LOGS_EXCEPTIONS'),
			'url' => static::getUrlLogFile('exceptions.log', $lang),
		];
		$items[] = [
			'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_LOGS_EMAIL'),
			'url' => static::getUrlLogFile('mailer.log', $lang),
		];
		$items[] = [
			'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_LOGS_LIST'),
			'url' => static::getUrlLogList($lang),
		];

		return $items;
	}

	protected static function getLogFileItem(string $title, string $name, string $lang): array
	{
		return [
			'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_LOGS_FACTORY', [
				'#LOGGER_NAME#' => $title,
			]),
			'url' => static::getUrlLogFile($name.'.log', $lang),
		];
	}

	protected static function getEventLogItems(string $lang, bool $isPerfmonInstalled): array
	{
		$items = array_map(
			static function(string $auditType) use ($lang): array
			{
				return [
					'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_EVENT_LOG_FACTORY', [
						'#LOGGER_NAME#' => ucwords(mb_strtolower(str_replace('SH_PROBLEMS_', '', $auditType))),
					]),
					'url' => static::getUrlEventLog($auditType, $lang),
				];
			},
			Constants::getAuditTypeList()
		);

		if($isPerfmonInstalled)
		{
			$items[] = [
				'text' => (string)Loc::getMessage('SH_PROBLEMS_MENU_SALE_PAY_SYSTEM_ERR_LOG'),
				'url' => static::getUrlSalePaymentErrorLog($lang),
			];
		}

		return $items;
	}

	/**
	 * Кладёт страницу логов в /bitrix/admin, если её там нет.
	 *
	 * Заглушку пишет установщик. Но портал, обновлённый с 1.x заменой файлов,
	 * установщик не проходил — и пункты «Логи» вели бы в 404. Переустановка не
	 * выход: она стирает настройки. Поэтому меню, которое строится только у
	 * администратора, пишет недостающую заглушку само — тем же
	 * AdminPage::install(), с путём туда, где модуль стоит. Чужой файл на
	 * этом месте не трогает.
	 *
	 * @param string $documentRoot корень сайта
	 * @param string $moduleDir каталог модуля, абсолютный
	 * @return bool страница на месте и ведёт в этот модуль
	 */
	public static function ensureLogsPage(string $documentRoot, string $moduleDir): bool
	{
		return AdminPage::install($documentRoot, $moduleDir);
	}
	
	// region Адреса ////
	/**
	 * Просмотр файла лога страницей модуля.
	 *
	 * @param string $fileName имя файла в каталоге логов, с расширением
	 */
	public static function getUrlLogFile(string $fileName, string $lang): string
	{
		return static::LOGS_PAGE.'?'.http_build_query([
			'lang' => $lang,
			'file' => $fileName,
		]);
	}
	
	/**
	 * Список файлов в каталоге логов.
	 */
	public static function getUrlLogList(string $lang): string
	{
		return static::LOGS_PAGE.'?'.http_build_query([
			'lang' => $lang,
		]);
	}
	
	/**
	 * Журнал событий, отфильтрованный по типу.
	 */
	public static function getUrlEventLog(string $auditType, string $lang): string
	{
		return '/bitrix/admin/event_log.php?'.http_build_query([
			'lang' => $lang,
			'set_filter' => 'Y',
			'adm_filter_applied' => 0,
			'find_type' => 'audit_type_id',
			'find_audit_type' => [$auditType],
		]);
	}

	/**
	 * Таблица ошибок платёжных систем — через просмотр таблиц perfmon.
	 */
	public static function getUrlSalePaymentErrorLog(string $lang): string
	{
		return '/bitrix/admin/perfmon_table.php?'.http_build_query([
			'lang' => $lang,
			'table_name' => 'b_sale_pay_system_err_log',
		]);
	}

	public static function getUrlSettings(string $lang): string
	{
		return '/bitrix/admin/settings.php?'.http_build_query([
			'lang' => $lang,
			'mid' => Constants::MODULE_ID,
		]);
	}
	// endregion ////
}
