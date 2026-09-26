<?php declare(strict_types=1);

use Bitrix\Main\Application;

if(file_exists(__DIR__.'/../../php_interface/def-functions.php'))
{
	require_once __DIR__.'/../../php_interface/def-functions.php';
}

if(!function_exists('_pr'))
{
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
					$dRoot = str_replace("/", "\\", $dRoot);
					$value["file"] = str_replace($dRoot, "", (string)$value["file"]);
					$dRoot = str_replace("\\", "/", $dRoot);
					$value["file"] = str_replace($dRoot, "", (string)$value["file"]);
				?>File: <b><?=$value["file"]?></b> [line: <?=$value["line"]?>]<br><?php
				endforeach
			?>
			</div>
			<pre style="color:#000; padding:10px;"><?php
				is_array($o)
					? print_r($o)
					: print_r(htmlspecialcharsbx($o));
			?></pre>
			</div><?php
		}
	}
}

if(!function_exists('_log'))
{
	function _log(array $value = []): void
	{
		$e = new \Exception();
		\Bitrix\Main\IO\File::putFileContents(
			\Shef\Problems\Main\Constants::getLogFullPath('log-custom'),
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
	function _log1(array $value = []): void
	{
		static $isFirstCall = false;
		$mode = FILE_APPEND;
		if(!$isFirstCall)
		{
			$isFirstCall = true;
			$mode = 0; // REWRITE ////
		}

		$e = new \Exception();
		file_put_contents(
			\Shef\Problems\Main\Constants::getLogFullPath('log1-custom'),
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