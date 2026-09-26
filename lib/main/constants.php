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
	 * Ключ в /bitrix/.settings.php (или .settings_extra.php), которым проект
	 * задаёт свой каталог логов:
	 *
	 *   'shef.problems' => ['value' => ['logDir' => '/var/log/portal'], 'readonly' => true],
	 */
	public const SETTINGS_KEY = 'shef.problems';
	public const SETTINGS_LOG_DIR = 'logDir';

	/**
	 * Имя каталога логов по умолчанию — рядом с корнем сайта.
	 */
	public const LOG_DIR_NAME = 'sh_log';

	/**
	 * Каталог логов — абсолютный путь, ВНЕ корня сайта.
	 *
	 * По умолчанию — на уровень выше корня: при корне /home/bitrix/www это
	 * /home/bitrix/sh_log. До 2.0.0 логи лежали в /local/sh_log, под корнем
	 * сайта, и скачать их мог любой, кто угадал имя файла, — а имена
	 * предсказуемы, в них трассировки и данные запросов. Вне корня сайта
	 * веб-сервер их не отдаст, настраивать ничего не нужно.
	 *
	 * Проект может задать свой каталог — ключ SETTINGS_KEY в
	 * /bitrix/.settings_extra.php. Принимается только абсолютный путь; что-то
	 * другое — каталог по умолчанию: относительный путь зависел бы от текущего
	 * каталога процесса, и агент писал бы в одно место, а страница — в другое.
	 *
	 * Корня сайта нет (CLI без DOCUMENT_ROOT) — временный каталог системы, а
	 * не «/sh_log» в корне файловой системы.
	 *
	 * @see docs/security.md
	 * @return string без «/» на конце
	 */
	public static function getLogDir(): string
	{
		$settings = Config\Configuration::getValue(static::SETTINGS_KEY);
		$custom = is_array($settings) ? ($settings[static::SETTINGS_LOG_DIR] ?? null) : null;

		if(is_string($custom) && str_starts_with($custom, '/') && rtrim($custom, '/') !== '')
		{
			return rtrim($custom, '/');
		}

		$documentRoot = rtrim(str_replace('\\', '/', (string)Application::getDocumentRoot()), '/');
		if($documentRoot === '')
		{
			return rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/'.static::LOG_DIR_NAME;
		}

		$parent = dirname($documentRoot);

		return ($parent === '/' ? '' : $parent).'/'.static::LOG_DIR_NAME;
	}

	/**
	 * Полный путь к файлу лога: <каталог логов>/<имя>.log.
	 *
	 * @param string $name имя без .log
	 * @return string
	 */
	public static function getLogFullPath(string $name): string
	{
		return static::getLogDir().'/'.$name.'.log';
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
