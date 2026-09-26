<?php
// Заглушка: каталог модуля браузеру недоступен, поэтому страницу в
// /bitrix/admin кладёт установщик, а сама страница живёт в модуле.
// Модуль может стоять и в /local/modules.
$shProblemsLogsPage = $_SERVER['DOCUMENT_ROOT'].'/local/modules/shef.problems/admin/logs.php';
require is_file($shProblemsLogsPage)
	? $shProblemsLogsPage
	: $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/shef.problems/admin/logs.php';
