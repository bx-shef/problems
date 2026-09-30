<?php declare(strict_types=1);

/**
 * Просмотр логов модуля — только администратору.
 *
 * Каталог логов лежит вне корня сайта (Constants::getLogDir()): ни прямая
 * ссылка, ни файловый менеджер Битрикса туда не дотянутся, и это нарочно.
 * Эта страница — единственный способ посмотреть лог из браузера.
 *
 * Открывается через /bitrix/admin/shef_problems_logs.php — заглушку в одну
 * строку, которую пишет Main\AdminPage (каталог модуля браузеру недоступен).
 *
 * Без параметра — список файлов; ?file=<имя> — конец файла. Имя проверяет
 * LogFiles::resolve(): шаблон имени и путь внутри каталога логов после
 * разрешения ссылок. Всё содержимое экранируется: в логах лежит ввод
 * посетителей.
 */

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Problems\Integration\Main\AdminMenu;
use Shef\Problems\Main\LogFiles;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

/** @var \CMain $APPLICATION */
/** @var \CUser $USER */
global $APPLICATION, $USER;

if (!($USER instanceof \CUser) || !$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('SH_PROBLEMS_LOGS_ACCESS_DENIED'));
}

if (!Loader::includeModule('shef.problems')) {
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
    \CAdminMessage::ShowMessage('Module shef.problems is not installed');
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$lang = (string)(Context::getCurrent()?->getLanguage() ?: LANGUAGE_ID);
$name = Context::getCurrent()?->getRequest()->getQuery('file');
// ?file[]=… — массив, а не имя: как «файла нет», без warning.
$name = is_string($name) ? $name : '';
$files = LogFiles::create();

$APPLICATION->SetTitle(
    $name === ''
    ? Loc::getMessage('SH_PROBLEMS_LOGS_TITLE')
    // Имя — из запроса, заголовок уходит в страницу: экранируется, как всё.
    : Loc::getMessage('SH_PROBLEMS_LOGS_TITLE_FILE', ['#FILE#' => $escape($name)])
);

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

if ($name === '') {
    // region Список ////
    $list = $files->getList();
    ?>
    <p><?=$escape((string)Loc::getMessage('SH_PROBLEMS_LOGS_DIR', ['#DIR#' => $files->getDir()]))?></p>
    <?php if (empty($list)): ?>
        <?php \CAdminMessage::ShowNote(Loc::getMessage('SH_PROBLEMS_LOGS_EMPTY')); ?>
    <?php else: ?>
        <table class="internal" style="width: 100%;">
            <tr class="heading">
                <td><?=$escape((string)Loc::getMessage('SH_PROBLEMS_LOGS_COL_NAME'))?></td>
                <td><?=$escape((string)Loc::getMessage('SH_PROBLEMS_LOGS_COL_SIZE'))?></td>
                <td><?=$escape((string)Loc::getMessage('SH_PROBLEMS_LOGS_COL_MODIFIED'))?></td>
            </tr>
            <?php foreach ($list as $file): ?>
                <tr>
                    <td><a href="<?=$escape(AdminMenu::getUrlLogFile($file['name'], $lang))?>"><?=$escape($file['name'])?></a></td>
                    <td style="text-align: right;"><?=$escape(\CFile::FormatSize($file['size']))?></td>
                    <td><?=$escape(date('d.m.Y H:i:s', $file['modified']))?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif;
    // endregion ////
} else {
    // region Файл ////
    $path = $files->resolve($name);
    ?>
    <p><a href="<?=$escape(AdminMenu::getUrlLogList($lang))?>">&larr; <?=$escape((string)Loc::getMessage('SH_PROBLEMS_LOGS_BACK'))?></a></p>
    <?php
    if (null === $path) {
        \CAdminMessage::ShowMessage(Loc::getMessage('SH_PROBLEMS_LOGS_NOT_FOUND', ['#FILE#' => $escape($name)]));
    } else {
        $tail = $files->tail($path);
        if ($tail['truncated']) {
            \CAdminMessage::ShowNote(Loc::getMessage('SH_PROBLEMS_LOGS_TRUNCATED', [
                '#SHOWN#' => \CFile::FormatSize(LogFiles::TAIL_BYTES),
                '#SIZE#' => \CFile::FormatSize($tail['size']),
            ]));
        }
        ?>
        <pre style="white-space: pre-wrap; word-break: break-all; background: #fff; padding: 10px; border: 1px solid #d7dde2;"><?=$escape($tail['content'])?></pre>
        <?php
    }
    // endregion ////
}

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
