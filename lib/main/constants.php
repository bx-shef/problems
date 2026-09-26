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

	/**
	 * Кому уходит проблема, если настройка не задана или испорчена.
	 * Пользователь 1 — первый администратор портала.
	 */
	public const DEFAULT_USER_ID = 1;

	// region Расширения фронта ////
	/**
	 * Имена расширений Битрикса: <каталог в /bitrix/js>.<подкаталог>.
	 *
	 * Каталог — через дефис, а не через точку: так называются каталоги
	 * расширений. Установщик кладёт install/js в /bitrix/js (карта в
	 * .settings.php, ключ installDir), ядро находит расширение по имени.
	 * Разойдутся имя и раскладка — стили молча не подключатся, ошибки не
	 * будет. Сходимость проверяет tests/assets_test.php.
	 */
	public const EXTENSION_PR_HTML = 'shef-problems.monolog-pr-html';
	public const EXTENSION_PR_HTML_ADMIN = 'shef-problems.monolog-pr-html-admin';

	/**
	 * @return string[]
	 */
	public static function getExtensionList(): array
	{
		return [
			static::EXTENSION_PR_HTML,
			static::EXTENSION_PR_HTML_ADMIN,
		];
	}

	public static function getPublicJsDir(): string
	{
		return '/bitrix/js/'.str_replace('.', '-', static::MODULE_ID);
	}
	// endregion ////

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
	 * Папка для хранения логов, от корня сайта.
	 *
	 * ⚠ Каталог лежит под корнем сайта. Закройте его на веб-сервере — иначе
	 * логи с трассировками и данными запросов скачает любой, кто знает адрес.
	 * @see docs/security.md
	 *
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
			return static::DEFAULT_USER_ID;
		}

		return \Shef\Options\Main\Constants::getSystemUserId();
	}

	/**
	 * ID из настройки модуля (пользователь, группа) — строго.
	 *
	 * Не (int) и не intval(): intval('5 62') даёт 5, intval(true) — 1, и
	 * опечатка в настройке молча отправила бы проблему не тому человеку.
	 * Принимается только целое больше нуля либо строка из одних цифр без
	 * ведущего нуля; всё остальное — DEFAULT_USER_ID.
	 *
	 * @param string $code код опции, например DEF_adminid
	 * @return int
	 */
	public static function getIdOption(string $code): int
	{
		$value = Config\Option::get(
			static::MODULE_ID,
			$code,
			(string) static::DEFAULT_USER_ID
		);

		if(is_int($value))
		{
			return $value > 0 ? $value : static::DEFAULT_USER_ID;
		}

		if(is_string($value) && 1 === preg_match('/^[1-9][0-9]*$/', $value))
		{
			return (int) $value;
		}

		return static::DEFAULT_USER_ID;
	}

	public static function getDefUserId(): int
	{
		return static::getIdOption('DEF_defuserid');
	}

	public static function getAdminId(): int
	{
		return static::getIdOption('DEF_adminid');
	}

	public static function getDirectorId(): int
	{
		return static::getIdOption('DEF_dirid');
	}

	public static function getSyncUserId(): int
	{
		return static::getIdOption('DEF_syncuserid');
	}

	public static function getProductsUserId(): int
	{
		return static::getIdOption('DEF_productsuserid');
	}

	public static function getSaleUserId(): int
	{
		return static::getIdOption('DEF_saleuserid');
	}
	// endregion ////

	// region Groups ////
	/**
	 * В какую группу складывать задачи.
	 *
	 * ⚠ Вкладка GRP на странице настроек выключена (options_conf.php), так что
	 * задать значение негде и метод всегда отдаёт умолчание. Оставлен как
	 * публичный API — см. «Известные шероховатости» в CLAUDE.md.
	 */
	public static function getGroupIdTask(): int
	{
		return static::getIdOption('GRP_tskid');
	}

	/**
	 * @see getGroupIdTask() — та же оговорка про выключенную вкладку.
	 */
	public static function getGroupIdIntegrateB24(): int
	{
		return static::getIdOption('GRP_intb24id');
	}
	// endregion ////
}
