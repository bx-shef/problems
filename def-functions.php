<?php declare(strict_types=1);

/**
 * Функции быстрой отладки: _pr(), _log(), _log1().
 *
 * Каждая закрыта function_exists, и порядок имеет значение: версия проекта
 * из php_interface/def-functions.php главнее, затем этот файл, затем
 * shef.options. @see include.php
 *
 * Логи пишутся в каталог логов модуля — Constants::getLogDir(), вне корня сайта.
 */

use Bitrix\Main\Application;

if(file_exists(__DIR__.'/../../php_interface/def-functions.php'))
{
	require_once __DIR__.'/../../php_interface/def-functions.php';
}

if(!function_exists('_pr'))
{
	/**
	 * Вывод на экран — только администратору, если не попросили иначе.
	 *
	 * Экранируется всё, в том числе массивы: в них бывает ввод пользователя,
	 * а смотрит на вывод администратор.
	 */
	function _pr($o, bool $show = false): void
	{
		if($show || \Bitrix\Main\Engine\CurrentUser::get()?->isAdmin() === true)
		{
			$bt = debug_backtrace();
			$dRoot = Application::getDocumentRoot();
			?>
			<div style="font-size:9pt; color:#000; background:#fff; border:1px dashed #000;">
			<div style="padding:3px 5px; background:#99CCFF;">
				<?php foreach($bt as $value):
					// У кадра встроенной функции нет file и line.
					$file = (string)($value['file'] ?? '');
					$dRoot = str_replace("/", "\\", $dRoot);
					$file = str_replace($dRoot, "", $file);
					$dRoot = str_replace("\\", "/", $dRoot);
					$file = str_replace($dRoot, "", $file);
				?>File: <b><?=htmlspecialcharsbx($file)?></b> [line: <?=(int)($value['line'] ?? 0)?>]<br><?php
				endforeach
			?>
			</div>
			<pre style="color:#000; padding:10px;"><?=htmlspecialcharsbx(print_r($o, true))?></pre>
			</div><?php
		}
	}
}

if(!function_exists('_log'))
{
	/**
	 * Запись в файл лога, дописыванием.
	 *
	 * Сигнатура та же, что у _log() из shef.options: его трейт TraitList\Log
	 * зовёт _log($value, static::getLogFile()), и какая бы из двух версий ни
	 * победила, вызов обязан работать.
	 *
	 * @param array $value что записать
	 * @param string $fileName имя файла без .log в каталоге логов модуля
	 */
	function _log(array $value = [], string $fileName = 'log-custom'): void
	{
		$e = new \Exception();
		\Bitrix\Main\IO\File::putFileContents(
			\Shef\Problems\Main\Constants::getLogFullPath($fileName),
			implode(PHP_EOL, [
				'['.date('Y-m-d H:i:s').']',
				print_r($value, true),
				'>>> trace >>>',
				print_r(str_replace(Application::getDocumentRoot(), '', $e->getTraceAsString()), true),
				'>>> >>> >>>',
				''
			]),
			\Bitrix\Main\IO\File::APPEND
		);
	}
}

if(!function_exists('_log1'))
{
	/**
	 * Запись в файл лога; первый вызов за запрос файл перезаписывает.
	 *
	 * Полезно, когда в логе нужна только одна цепочка вызовов.
	 *
	 * @param array $value что записать
	 * @param string $fileName имя файла без .log в каталоге логов модуля
	 */
	function _log1(array $value = [], string $fileName = 'log1-custom'): void
	{
		static $isFirstCall = false;
		$mode = FILE_APPEND;
		if(!$isFirstCall)
		{
			$isFirstCall = true;
			$mode = 0; // REWRITE ////
		}

		$file = \Shef\Problems\Main\Constants::getLogFullPath($fileName);
		$dir = dirname($file);
		if(!is_dir($dir))
		{
			mkdir($dir, 0775, true);
		}

		$e = new \Exception();
		file_put_contents(
			$file,
			implode(PHP_EOL, [
				'['.date('Y-m-d H:i:s').']',
				print_r($value, true),
				'>>> trace >>>',
				print_r(str_replace(Application::getDocumentRoot(), '', $e->getTraceAsString()), true),
				'>>> >>> >>>',
				''
			]),
			$mode
		);
	}
}
