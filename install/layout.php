<?php

declare(strict_types=1);

/**
 * Раскладка shef.problems вне каталога модуля — для портала, который выкладывается из git
 * (bx-shef/options#55, читает Main\Modules\Layout shef.options без ядра): то же, что кладёт
 * InstallFiles() — installDir (install/js → /bitrix/js) и заглушка страницы логов (Main\AdminPage).
 */

require_once dirname(__DIR__).'/lib/main/adminpage.php';

return static fn (string $documentRoot, string $moduleDir): array => [
    'copy' => [['from' => '/install/js', 'to' => '/bitrix/js']],
    'files' => [
        substr(\Shef\Problems\Main\AdminPage::getTarget($documentRoot), strlen(rtrim($documentRoot, '/'))) => \Shef\Problems\Main\AdminPage::getContent($documentRoot, $moduleDir),
    ],
];
