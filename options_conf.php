<?php declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;
use Shef\Problems\Main\Constants;

/**
 * Опции для страницы настроек
 * 
 * языковой файл options.php
 *
 * Tab(prefix)->Option(code) ~> код свойства: prefix_code
 */

$response = ShOptionsConfig::getInstance(
	moduleId: 'shef.problems',
	indexDoc: 'README.md'
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