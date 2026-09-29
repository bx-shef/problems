<?php

use Bitrix\Main\Event;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Config;
use Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable;
use Bitrix\Intranet\CustomSection\Entity\CustomSectionTable;
use Bitrix\Intranet\CustomSection\Entity\EO_CustomSection;
use Bitrix\Intranet\CustomSection\Entity\EO_CustomSectionPage;

if(class_exists('shef_problems'))
{
	return;
}

Loc::loadMessages(__FILE__);

Class shef_problems
	extends CModule
{
	public $MODULE_ID = 'shef.problems';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_SORT;
	public $MODULE_GROUP_RIGHTS = 'Y';

	public $PARTNER_NAME;
	public $PARTNER_URI;
	
	/** @var string  */
	public $PHP_MIN_VER = '8.2.0';
	/** @var string  */
	public $NEED_MAIN_VERSION = '22.600.300';
	/** @var array  */
	public $NEED_MODULES = [];
	/**
	 * shef.options нужен не любой: страница настроек и выбор источника
	 * Monolog опираются на то, что появилось в 3.0.0 (ShOptionsConfig без
	 * indexDoc, ShProjectContext в project-context.php).
	 *
	 * @var array
	 */
	public $NEED_MODULES_BY_VERSION = [
		'shef.options' => '3.0.0',
	];
	
	/** @var \CMain  */
	private $application;
	
	public function __construct()
	{
		$arModuleVersion = [];
		
		require __DIR__.'/version.php';

		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		
		$this->MODULE_NAME = Loc::getMessage('shef.problems_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('shef.problems_MODULE_DESC');
		
		$sort = 'MODULE_SORT';
		$this->{$sort} = (int)'1002';

		$this->PARTNER_NAME = Loc::getMessage('shef.problems_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('shef.problems_PARTNER_URI');
		
		$this->application = $GLOBALS['APPLICATION'];
	}
	
	// region DB && Register Module ////
	/**
	 * Register Module
	 *
	 * @param array $arParams
	 * @return bool
	 * @throws \Bitrix\Main\LoaderException
	 *
	 * @memo Need call first
	 */
	public function InstallDB(array $arParams = []): bool
	{
		RegisterModule($this->MODULE_ID);
		
		try
		{
			\Bitrix\Main\Loader::includeModule($this->MODULE_ID);
			
			$this->installLeftMenu();
		}
		catch(\Throwable $throwable)
		{
		
		}
		
		
		return true;
	}
	
	/**
	 * UnRegister Module
	 *
	 * @param array $arParams ключ savedata = 'Y' оставляет настройки модуля
	 * @return bool
	 * @throws \Bitrix\Main\LoaderException
	 *
	 * @memo Need call last
	 */
	public function UnInstallDB(array $arParams = []): bool
	{
		try
		{
			\Bitrix\Main\Loader::includeModule($this->MODULE_ID);
		}
		catch(\Bitrix\Main\LoaderException $loaderException)
		{
		
		}
		
		$this->unInstallLeftMenu();
		
		/**
		 * Настройки уходят вместе с модулем.
		 *
		 * Раньше b_option оставался нетронутым: строки снятого модуля лежали
		 * на портале дальше, а повторная установка молча поднимала прежние
		 * значения — «поставить начисто» было нельзя.
		 *
		 * Ключ savedata — уговор ядра: установщик с формой удаления кладёт
		 * сюда ответ на «сохранить данные?». Формы у модуля нет, поэтому
		 * умолчание — чистить; появится форма — метод её уже слушает.
		 */
		if(($arParams['savedata'] ?? 'N') !== 'Y')
		{
			Config\Option::delete($this->MODULE_ID);
		}
		
		UnRegisterModule($this->MODULE_ID);
		
		$GLOBALS['CACHE_MANAGER']->CleanAll();
		
		return true;
	}
	// endregion ////
	
	// region LeftMenu ////
	private function getLeftMenuList(): array
	{
		$configuration = Config\Configuration::getInstance($this->MODULE_ID);
		$list = $configuration->get('installLeftMenu');
		if(!is_array($list))
		{
			$list = [];
		}
		
		return $list;
	}
	
	public function installLeftMenu(): bool
	{
		if(!ModuleManager::isModuleInstalled('intranet'))
		{
			return false;
		}
		
		/** @var CustomSectionTable $sectionDataManager */
		$sectionDataManager = CustomSectionTable::class;
		
		/** @var CustomSectionPageTable $pageDataManager */
		$pageDataManager = CustomSectionPageTable::class;
		
		foreach($this->getLeftMenuList() as $sectionFields)
		{
			if(isset($sectionFields['moduleId']))
			{
				$section = $sectionDataManager::getList([
					'filter' => [
						'=MODULE_ID' => (string)$sectionFields['moduleId'],
						'=CODE' => (string)$sectionFields['code']
					],
					'select' => [
						'ID',
						'MODULE_ID'
					]
				])->fetchObject();
			}
			else
			{
				$section = $sectionDataManager::createObject();
				$section->setModuleId($this->MODULE_ID);
				$section->setTitle((string)$sectionFields['title']);
				$section->setCode((string)$sectionFields['code']);
				
				$response = $section->save();
				if(!$response->isSuccess())
				{
					return false;
				}
			}
			
			if(empty($section))
			{
				return false;
			}
			
			foreach((array)$sectionFields['pages'] as $pageFields)
			{
				$page = ($pageDataManager::createObject())
					->setModuleId($section->getModuleId())
					->setCustomSection($section)
					->setCode((string)$pageFields['code'])
					->setTitle((string)$pageFields['title'])
					->setSort((int)$pageFields['sort'])
					->setSettings((string)$pageFields['settingsRow'])
				;
				
				$response = $page->save();
				if(!$response->isSuccess())
				{
					return false;
				}
			}
		}
		
		return true;
	}
	
	public function unInstallLeftMenu(): bool
	{
		if(!IsModuleInstalled('intranet'))
		{
			return false;
		}
		
		/** @var CustomSectionTable $sectionDataManager */
		$sectionDataManager = CustomSectionTable::class;
		
		/** @var CustomSectionPageTable $pageDataManager */
		$pageDataManager = CustomSectionPageTable::class;
		
		$list = $sectionDataManager::getList([
			'filter' => [
				'=MODULE_ID' => $this->MODULE_ID
			]
		])->fetchCollection()->getAll();
		
		array_walk(
			$list,
			function(EO_CustomSection $section)
			{
				$section->delete();
			}
		);
		
		foreach($this->getLeftMenuList() as $sectionFields)
		{
			if(isset($sectionFields['moduleId']))
			{
				$pageList = $pageDataManager::getList([
					'filter' => [
						'=MODULE_ID' => (string)$sectionFields['moduleId'],
						'=CODE' => array_column((array)$sectionFields['pages'], 'code')
					],
					'select' => ['ID']
				])->fetchCollection()->getAll();
				
				array_walk(
					$pageList,
					function(EO_CustomSectionPage $page)
					{
						$page->delete();
					}
				);
			}
		}
		
		return true;
	}
	// endregion ////
	
	// region Events ////
	private function getEventsList(): array
	{
		$configuration = Config\Configuration::getInstance($this->MODULE_ID);
		$list = $configuration->get('installEvents');
		if(!is_array($list))
		{
			$list = [];
		}
		
		return $list;
	}

	public function InstallEvents(): bool
	{
		$eventManager = EventManager::getInstance();

		foreach($this->getEventsList() as $event)
		{
			if($event['isCompatible'])
			{
				$eventManager->registerEventHandlerCompatible(
					$event['from']['module'],
					$event['from']['event'],
					$event['to']['module'],
					$event['to']['class'],
					$event['to']['function'],
					$event['to']['sort'] ?? 100,
				);
			}
			else
			{
				$eventManager->registerEventHandler(
					$event['from']['module'],
					$event['from']['event'],
					$event['to']['module'],
					$event['to']['class'],
					$event['to']['function'],
					$event['to']['sort'] ?? 100,
				);
			}
		}

		return true;
	}

	/**
	 * Обработчики, которые модуль регистрировал раньше, а теперь нет.
	 *
	 * До 2.0.0 пункты «Логи» и «Журналы» вешались на верхнюю панель через
	 * событие shef.uiclear. Замена файлов модуля регистрацию не снимает —
	 * снимает только деинсталляция, и только то, что она знает. Класс
	 * обработчика оставлен заглушкой, см.
	 * \Shef\Problems\Integration\Shef\UiClear\Events.
	 *
	 * @return array[]
	 */
	private function getLegacyEventsList(): array
	{
		return [
			[
				'from' => [
					'module' => 'shef.uiclear',
					'event' => 'onBitrixMenuExtInitTopPanelUserMenu'
				],
				'to' => [
					'module' => $this->MODULE_ID,
					'class' => '\Shef\Problems\Integration\Shef\UiClear\Events',
					'function' => 'onBitrixMenuExtInitTopPanelUserMenu'
				]
			],
		];
	}

	public function UnInstallEvents(): bool
	{
		$eventManager = EventManager::getInstance();
		
		// region init Event onModuleUnInstall ////
		$eventsList = $eventManager->findEventHandlers($this->MODULE_ID, 'onModuleUnInstall');
		if (!empty($eventsList))
		{
			$event = new Event($this->MODULE_ID, 'onModuleUnInstall');
			$event->send();
		}
		// endregion ////
		
		foreach(array_merge($this->getEventsList(), $this->getLegacyEventsList()) as $event)
		{
			$eventManager->unRegisterEventHandler(
				$event['from']['module'],
				$event['from']['event'],
				$event['to']['module'],
				$event['to']['class'],
				$event['to']['function'],
			);
		}

		return true;
	}
	// endregion ////
	
	// region Files ////
	private function getDirList(): array
	{
		$configuration = Config\Configuration::getInstance($this->MODULE_ID);
		$list = $configuration->get('installDir');
		if(!is_array($list))
		{
			$list = [];
		}
		
		return $list;
	}
	
	/**
	 * Публичные каталоги, которые модуль раскладывал раньше, а теперь нет.
	 *
	 * До 2.0.0 installDir копировал install/images в /bitrix/images: там
	 * лежали скриншоты к документации. Документация живёт в репозитории,
	 * скриншоты ушли вместе с ней — а удалить каталог на портале, обновлённом
	 * с 1.x, иначе некому.
	 *
	 * @return string[]
	 */
	private function getLegacyDirList(): array
	{
		return [
			'/bitrix/images/'.$this->MODULE_ID,
		];
	}
	
		/**
	 * Каталог модуля — там, где он стоит на самом деле: /bitrix/modules или
	 * /local/modules. Раньше здесь был зашитый /bitrix/modules, и модуль из
	 * /local/modules при установке не копировал ничего.
	 */
	private function getModuleDir(): string
	{
		return dirname(__DIR__);
	}
	
	/**
	 * Заглушка страницы логов в /bitrix/admin. Не копируется, а пишется: путь
	 * в ней зависит от того, где стоит модуль. Класс подключается явно — на
	 * автозагрузку классов модуля в установщике полагаться нельзя.
	 */
	private function getAdminPage(): string
	{
		require_once $this->getModuleDir().'/lib/main/adminpage.php';
		
		return \Shef\Problems\Main\AdminPage::class;
	}
	
	public function InstallFiles(array $arParams = []): bool
	{
		$docRoot = Application::getDocumentRoot();
		$fromPath = $this->getModuleDir();
		$toPath = $docRoot;
		
		foreach($this->getDirList() as $map)
		{
			\CopyDirFiles(
				$fromPath.$map['from'],
				$toPath.$map['to'],
				true,
				true
			);
		}
		
		$this->getAdminPage()::install($docRoot, $this->getModuleDir());
		
		return true;
	}
	
	public function UnInstallFiles(): bool
	{
		$response = $this->checkChildModules();

		if(!$response->isSuccess())
		{
			$this->application->ThrowException(
				join(PHP_EOL.'<br>',
					array_merge(
						[Loc::getMessage('SH_PROBLEM_UNINSTALL_MODULE')],
						$response->getErrorMessages()
					)
				)
			);
			
			return false;
		}
		
		$docRoot = Application::getDocumentRoot();
		$toPath = $docRoot;
		
		foreach($this->getLegacyDirList() as $legacyPath)
		{
			\Bitrix\Main\IO\Directory::deleteDirectory($toPath.$legacyPath);
		}
		
		// Только свою заглушку: проект мог положить на её место свой файл.
		$this->getAdminPage()::uninstall($docRoot, $this->getModuleDir());
		
		foreach($this->getDirList() as $map)
		{
			if($map['isNeedUnInstall'] === false)
			{
				continue;
			}
			
			if(
				isset($map['customPathUnInstall'])
				&& is_array($map['customPathUnInstall'])
				&& !empty($map['customPathUnInstall'])
			)
			{
				// Путь бывает и файлом: страница в /bitrix/admin — одна среди
				// файлов всех модулей, и удалять там можно только своё.
				foreach($map['customPathUnInstall'] as $customPath)
				{
					if(is_file($toPath.$customPath))
					{
						\Bitrix\Main\IO\File::deleteFile($toPath.$customPath);
						continue;
					}
					
					\Bitrix\Main\IO\Directory::deleteDirectory(
						$toPath.$customPath
					);
				}
			}
			else
			{
				$list = [
					$this->MODULE_ID,
					str_replace('.', '-', $this->MODULE_ID)
				];
				
				foreach($list as $moduleId)
				{
					\Bitrix\Main\IO\Directory::deleteDirectory(
						$toPath.$map['to'].'/'.$moduleId
					);
				}
			}
		}

		return true;
	}
	// endregion ////
	
	// region Install.Public ////
	public function DoInstall(): void
	{
		/**
		 * Модуль поставляется в UTF-8 и перекодировкой при установке не занимается.
		 * На проекте в CP1251 файлы модуля остались бы в UTF-8, и весь русский
		 * текст превратился бы в мусор уже после установки — молча.
		 * Поэтому отказываемся ставиться сразу, а не разбираемся потом.
		 */
		if(!Application::isUtfMode())
		{
			$this->ShowForm(
				'ERROR',
				Loc::getMessage('SH_NEED_UTF8')
			);
		}
		
		$phpVer = phpversion();
		
		if(version_compare($phpVer, $this->PHP_MIN_VER, '<'))
		{
			$this->ShowForm(
				'ERROR',
				Loc::getMessage('SH_NEED_PHP_VER', [
					'#CURRENT#' => $phpVer,
					'#NEED#' => $this->PHP_MIN_VER,
				])
			);
		}
		
		if(
			is_array($this->NEED_MODULES)
			&& !empty($this->NEED_MODULES)
		){
			foreach($this->NEED_MODULES as $module)
			{
				if(!ModuleManager::isModuleInstalled($module))
				{
					$this->ShowForm(
						'ERROR',
						Loc::getMessage('SH_NEED_MODULES', [
							'#NEED#' => $module
						])
					);
				}
			}
		}
		
		if(
			!empty($this->NEED_MODULES_BY_VERSION)
			&& is_array($this->NEED_MODULES_BY_VERSION)
		){
			foreach($this->NEED_MODULES_BY_VERSION as $module => $ver)
			{
				if(
					!ModuleManager::isModuleInstalled($module)
					|| version_compare(
						(string)ModuleManager::getVersion($module),
						$ver
					) < 0
				)
				{
					$this->ShowForm(
						'ERROR',
						Loc::getMessage('SH_NEED_MODULES_BY_VERSION', [
							// Модули линейки shef.* поставляются с GitHub: страница
							// Marketplace для них отвечает «снят с публикации».
							'#URL#' => (strpos($module, '.') === false
								? 'https://www.1c-bitrix.ru/products/cms/versions.php?module='.$module
								: (str_starts_with($module, 'shef.')
									? 'https://github.com/bx-shef/'.substr($module, 5).'/releases'
									: 'https://marketplace.1c-bitrix.ru/solutions/'.$module.'/'
								)
							),
							'#NEED#' => $module,
							'#VER#' => $ver
						])
					);
				}
			}
		}

		if(
			mb_strlen($this->NEED_MAIN_VERSION) <= 0
			|| version_compare(SM_VERSION, $this->NEED_MAIN_VERSION) >= 0
		)
		{
			$response = $this->InstallDB();
			$response = $this->InstallEvents();
			$response = $this->InstallFiles();

			$this->ShowForm(
				'OK',
				Loc::getMessage('SH_MOD_INST_OK')
			);
		}
		else
		{
			$this->ShowForm(
				'ERROR',
				Loc::getMessage('SH_NEED_RIGHT_VER', [
					'#NEED#' => $this->NEED_MAIN_VERSION
				])
			);
		}
	}

	public function DoUninstall(): void
	{
		$response = $this->UnInstallFiles();
		if(!$response)
		{
			/** @var \CApplicationException $problem */
			$problem = $this->application->GetException();
			$this->ShowForm(
				'ERROR',
				(
					$problem instanceof \CApplicationException
					? $problem->GetString()
					: 'Error Uninstall'
				)
			);
		}
		$response = $this->UnInstallEvents();
		$response = $this->UnInstallDB();
	}

	private function ShowForm(
		string $type,
		string $message,
		string $buttonName = ''
	): void
	{
		/** @memo need for show title at prolog */
		$keys = array_keys($GLOBALS);
		for($i = 0; $i < count($keys); $i++)
		{
			if(
				$keys[$i] != 'i'
				&& $keys[$i] != 'GLOBALS'
				&& $keys[$i] != 'strTitle'
				&& $keys[$i] != 'filepath'
			)
			{
				global ${$keys[$i]};
			}
		}
		global $APPLICATION, $adminPage, $adminMenu, $adminChain, $USER;
		
		$this->application->SetTitle(
			Loc::getMessage('shef.problems_MODULE_NAME')
		);

		include(Application::getDocumentRoot().'/bitrix/modules/main/include/prolog_admin_after.php');

		\CAdminMessage::ShowMessage([
			'MESSAGE' => $message,
			'TYPE' => $type,
			'HTML' => true
		]);
		?>
		<form action="<?=$this->application->GetCurPage()?>" method="get">
			<p>
				<input type="hidden" name="lang" value="<?=LANG?>">
				<input type="submit" value="<?=($buttonName <> '' ? $buttonName : Loc::getMessage('SH_MOD_BACK'))?>">
			</p>
		</form>
		<?php
		include(Application::getDocumentRoot().'/bitrix/modules/main/include/epilog_admin.php');
		die();
	}
	// endregion /////
	
	// region Tools ////
	private function checkChildModules(): Result
	{
		$result = new Result();
		foreach(ModuleManager::getInstalledModules() as $moduleId => $moduleDesc)
		{
			if($this->MODULE_ID === $moduleId)
			{
				continue;
			}
			
			$requireModules = Config\Configuration::getInstance($moduleId)->get('requireModules');
			if(empty($requireModules) || !is_array($requireModules))
			{
				continue;
			}
			
			if(in_array($this->MODULE_ID, $requireModules))
			{
				$result->addError(new Error(Loc::getMessage('SH_NEED_UNINSTALL_MODULE_BEFORE', [
					'#TARGET#' => $moduleId
				])));
			}
		}
		
		return $result;
	}
	// endregion /////
}