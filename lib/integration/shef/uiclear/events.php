<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Shef\UiClear;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Shef\Problems\Main\Constants;

/**
 * Заглушка для порталов, обновлённых с 1.x.
 *
 * До 2.0.0 модуль вешал пункты «Логи» и «Журналы» на верхнюю панель через
 * событие shef.uiclear:onBitrixMenuExtInitTopPanelUserMenu. С 2.0.0 от
 * shef.uiclear модуль не зависит, а пункты живут в меню административной
 * части (admin/menu.php).
 *
 * Но регистрация обработчика в b_module_to_module при замене файлов модуля
 * никуда не девается — её снимает только деинсталляция. Удали мы класс, и на
 * портале, где shef.uiclear стоит, событие звало бы то, чего нет. Поэтому
 * класс остаётся и честно отвечает «мне нечего добавить», а установщик
 * снимает регистрацию при удалении (\shef_problems::getLegacyEventsList()).
 *
 * Удалять вместе со следующей мажорной версией, когда порталов на 1.x не
 * останется.
 */
class Events
{
	public static function onBitrixMenuExtInitTopPanelUserMenu(Event $event): EventResult
	{
		return new EventResult(
			EventResult::UNDEFINED,
			null,
			Constants::MODULE_ID
		);
	}
}
