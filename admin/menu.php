<?php

declare(strict_types=1);

/**
 * Раздел модуля в меню административной части.
 *
 * Ядро подключает этот файл само — для каждого установленного модуля, при
 * построении меню. Регистрировать его не нужно, копировать тоже: файл
 * читается прямо из каталога модуля.
 *
 * Логи — только администратору: в них трассировки, пути и данные запросов.
 *
 * @see \Shef\Problems\Integration\Main\AdminMenu
 */

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Shef\Problems\Integration\Main\AdminMenu;

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

/** @var \CUser $USER */
global $USER;

if (!($USER instanceof \CUser) || !$USER->IsAdmin()) {
    return false;
}

if (!Loader::includeModule('shef.problems')) {
    return false;
}

$context = Context::getCurrent();

// Портал, обновлённый с 1.x заменой файлов, установщик не проходил, и страницы
// логов в /bitrix/admin у него нет. Не вышло положить — меню всё равно
// строим: журнал событий и настройки от неё не зависят.
AdminMenu::ensureLogsPage(
    (string)Application::getDocumentRoot(),
    dirname(__DIR__)
);

return AdminMenu::build(
    lang: (string)($context?->getLanguage() ?: LANGUAGE_ID),
    isPerfmonInstalled: ModuleManager::isModuleInstalled('perfmon'),
);
