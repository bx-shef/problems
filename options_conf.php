<?php declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;
use Shef\Problems\Integration\Main\AdminMenu;
use Shef\Problems\Main\Constants;

/**
 * Опции для страницы настроек
 * 
 * языковой файл options.php
 *
 * Tab(prefix)->Option(code) ~> код свойства: prefix_code
 */

// indexDoc больше не передаём: вкладка «Документация» ушла из shef.options в
// 3.0.0 вместе с параметром, и именованный аргумент, которого нет, — это
// Error «Unknown named parameter», то есть неоткрывающаяся страница настроек.
$response = ShOptionsConfig::getInstance(
	moduleId: 'shef.problems'
);
if(!$response->isSuccess())
{
	return $response;
}

/** @var ShOptionsConfig $options */
$options = $response->getData()['OPTIONS'];

$options->addTab(
	(new Options\Tab('DEF'))
		->setName(Loc::getMessage($options->moduleId.'_TAB_DEF_NAME'))
		->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_TITLE'))
		->addOption(
			(new Options\RowInfo('SystemUserId'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_SystemUserId', [
					'#ID#' => Constants::getSystemUserId(),
					'#LANG#' => LANGUAGE_ID
				]))
				->setType(Options\TypeUIAlert::Warning)
		)
		->addOption(
			// Логи лежат вне корня сайта, прямой ссылки на них нет: смотреть —
			// страницей модуля. Каталог показываем, чтобы было видно, куда
			// пишет модуль, — его может переопределить проект.
			(new Options\RowInfo('Logs'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_Logs', [
					'#URL#' => AdminMenu::getUrlLogList(LANGUAGE_ID),
					'#DIR#' => htmlspecialcharsbx(Constants::getLogDir()),
				]))
				->setType(Options\TypeUIAlert::Note)
		)
		->addOption(
			(new Options\Users('defuserid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_defuserid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_defuserid_descr'))
				->initSimpleUserList([
					'LOGIC' => 'OR',
					[
						'%=GROUPS.GROUP.STRING_ID' => 'EMPLOYEES_%'
					],
					[
						'=GROUPS.GROUP_ID' => 1
					]
				])->setShowRows(1)
		)
		->addOption(
			(new Options\Users('adminid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_adminid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_adminid_descr'))
				->initSimpleUserList([
					'=GROUPS.GROUP_ID' => 1
				])->setShowRows(1)
		)
		->addOption(
			(new Options\Users('dirid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_dirid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_dirid_descr'))
				->initSimpleUserList([
					'=GROUPS.GROUP_ID' => 1
				])->setShowRows(1)
		)
		->addOption(
			(new Options\Users('syncuserid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_syncuserid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_syncuserid_descr'))
				->initSimpleUserList([
					'=GROUPS.GROUP_ID' => 1
				])->setShowRows(1)
		)
		->addOption(
			(new Options\Users('productsuserid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_productsuserid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_productsuserid_descr'))
				->initSimpleUserList([
					'LOGIC' => 'OR',
					[
						'%=GROUPS.GROUP.STRING_ID' => 'EMPLOYEES_%'
					],
					[
						'=GROUPS.GROUP_ID' => 1
					]
				])->setShowRows(1)
		)
		->addOption(
			(new Options\Users('saleuserid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_saleuserid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_saleuserid_descr'))
				->initSimpleUserList([
					'LOGIC' => 'OR',
					[
						'%=GROUPS.GROUP.STRING_ID' => 'EMPLOYEES_%'
					],
					[
						'=GROUPS.GROUP_ID' => 1
					]
				])->setShowRows(1)
		)
);
// ⚠ Вкладка GRP ниже выключена переключателем-комментарием: первая его
// строка «слэш-звёздочка-слэш» открывает комментарий, последняя
// «слэш-слэш-звёздочка-слэш» закрывает. Допишите слэш в начало первой — и
// вкладка включится. Constants::getGroupIdTask() и getGroupIdIntegrateB24()
// читают её опции и пока отдают умолчание. См. «Известные шероховатости»
// в CLAUDE.md.
/*/
$options->addTab(
	(new Options\Tab('GRP'))
		->setName(Loc::getMessage($options->moduleId.'_TAB_GRP_NAME'))
		->setTitle(Loc::getMessage($options->moduleId.'_TAB_GRP_TITLE'))
		->addOption(
			(new Options\RowInfo('CreateGroup'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_GRP_CreateGroup', [
					'#LANG#' => LANGUAGE_ID
				]))
				->setType(Options\TypeUIAlert::Note)
		)
		->addOption(
			(new Options\NumberInt('intb24id'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_GRP_intb24id'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_GRP_intb24id_descr'))
		)
		->addOption(
			(new Options\NumberInt('tskid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_GRP_tskid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_GRP_tskid_descr'))
		)
);
//*/

return $options->get();