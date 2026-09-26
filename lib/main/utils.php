<?php declare(strict_types=1);

namespace Shef\Problems\Main;

use CBPCalc;
use CIBlockSection;
use CIntranetUtils;
use CUser;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Type;
use Bitrix\Main\Loader;
use Bitrix\Main\Config;
use Shef\Problems\Integration\BizProc;

class Utils
{
	/**
	 * Возвращает цепочку наследования объекта
	 *
	 * @param object $instance
	 * @return string
	 */
	public static function getAllParents(object $instance): string
	{
		return str_replace('\\', '\\',
			get_class($instance).'<br><-'
			.implode(
				'<br><-',
				array_reverse(
					class_parents($instance)
				)
			)
		);
	}
	
	/**
	 * Добавления рабочих дней
	 *
	 * Если установлен модуль bizproc -> считает через его калькулятор
	 * Если нет, то просто добавим дни
	 *
	 * Калькулятор бизнес-процессов — не публичный API, и на свежих ядрах он
	 * ломался: в рабочей копии 1.1.8 метод из-за этого свели к голому
	 * $date->add(), и рабочие дни считаться перестали везде. Здесь иначе:
	 * калькулятор пробуем, а упал или вернул не дату — прибавляем
	 * календарные дни. Выходные тогда не учтены, но дата есть.
	 *
	 * @param Type\Date $date
	 * @param string $interval -> 2D
	 * @return Type\Date
	 * @throws LoaderException
	 * @throws ObjectException
	 *
	 * @see: \Bitrix\Main\Type\Date::add
	 *
	 * @memo  ранее указывали так +(-)2d. Но не понятно что будет с натацией 6YT5M -> @see: \Bitrix\Main\Type\Date::add
	 */
	public static function workDateAdd(
		Type\Date $date,
		string $interval
	): Type\Date
	{
		if(!Loader::includeModule('bizproc'))
		{
			return $date->add($interval);
		}

		$formatDate = 'd.m.Y';
		try
		{
			$response = (new CBPCalc(
				(new BizProc\EmptyActivity('emptyActivity')))
			)->Calculate('=workdateadd("'.$date->format($formatDate).'","'.$interval.'")');
			
			if(is_string($response) && $response !== '')
			{
				return new Type\Date($response, $formatDate);
			}
		}
		catch(\Throwable $throwable)
		{
			// Калькулятор не справился — ниже календарные дни.
		}
		
		return $date->add($interval);
	}

	/**
	 * Возвращает руководителей сотрудника
	 * Первым будет непосредственный начальник
	 * Последний будет руководитель topLevel уровня
	 * Учитывает что сотрудник может работать в разных департаментах
	 * Уволенных сотрудников обрабатывает
	 *
	 * @param int $userId - сотрудник
	 * @param bool $skipAbsent - пропускать отсутствующих (отпуск и тп)
	 * @param bool $clearCache - сбросить кеш и поискать сначала
	 * @param int $topLevel - максимальный уровень вложенности
	 * @return array|int[]
	 * @throws ArgumentNullException
	 * @throws LoaderException
	 * @throws ObjectNotFoundException
	 */
	public static function getUserBoss(
		int $userId,
		bool $skipAbsent = true,
		bool $clearCache = false,
		int $topLevel = 5
	): array
	{
		// region Cache ////
		/**
		 * Кеш на время запроса. Имя не $list намеренно: ниже по методу $list
		 * был и временным списком разделов, и каждый вызов затирал кеш
		 * целиком, а чтение $list[$userId] без проверки давало на PHP 8
		 * «Undefined array key».
		 */
		static $cache = [];

		if($clearCache)
		{
			unset($cache[$userId]);
		}

		if(isset($cache[$userId]))
		{
			return $cache[$userId];
		}
		// endregion ////

		// region Load Modules ////
		$modules = [
			'iblock',
			'intranet'
		];
		foreach($modules as $module)
		{
			if(!Loader::includeModule($module))
			{
				throw new LoaderException('module '.$module.' not loaded');
			}
		}
		// endregion ////

		// region Init ////
		$departmentIdList = [];
		$departmentList = [];
		$result = [];

		$departmentIBlockId = (int)Config\Option::get('intranet', 'iblock_structure', 0);
		if($departmentIBlockId < 1)
		{
			throw new ArgumentNullException('departmentIBlockId');
		}
		// endregion ////

		// region Init.Department ////
		$cursor = CUser::GetByID($userId);
		if($row = $cursor->Fetch())
		{
			if(isset($row['UF_DEPARTMENT']))
			{
				if (!is_array($row['UF_DEPARTMENT']))
				{
					$row['UF_DEPARTMENT'] = [$row['UF_DEPARTMENT']];
				}
				$departmentIdList = $row['UF_DEPARTMENT'];
			}
		}
		else
		{
			throw new ObjectNotFoundException('user not found');
		}
		unset($cursor, $row);

		foreach($departmentIdList as $departmentId)
		{
			$list = [];
			$cursor = CIBlockSection::GetNavChain($departmentIBlockId, (int)$departmentId);
			while($row = $cursor->GetNext())
			{
				$list[] = (int)$row['ID'];
			}
			unset($cursor, $row);

			$departmentList[] = array_reverse($list);
		}
		// endregion ////

		// region Get Boss ////
		foreach($departmentList as $departmentItems)
		{
			$maxLevel = $topLevel;
			foreach($departmentItems as $level => $deptId)
			{
				if(
					$maxLevel > 0
					&& $level + 1 > $maxLevel
				)
				{
					break;
				}

				$cursor = CIBlockSection::GetList(
					[],
					[
						'IBLOCK_ID' => $departmentIBlockId,
						'ID' => $deptId,
					],
					false,
					['ID', 'UF_HEAD']
				);
				
				while($row = $cursor->Fetch())
				{
					$userHead = (int)$row['UF_HEAD'];
					if(
						$userHead === $userId
						|| $userHead <= 0
						|| ($skipAbsent && CIntranetUtils::IsUserAbsent($userHead))
					)
					{
						$maxLevel++;
						continue;
					}
					if (!in_array($userHead, $result))
					{
						$result[] = $userHead;
					}
				}
				unset($cursor, $row);
			}
		}
		// endregion ////

		$cache[$userId] = $result;
		return $cache[$userId];
	}
}