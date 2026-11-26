# Памятка агенту: опорные точки

> Последняя сверка: 2026-11-26

Подробности к [CLAUDE.md](../../../CLAUDE.md); правила владельца здесь не менее обязательны, чем в нём.

## Опорные точки

**Monolog — из двух мест.** `.settings.php` решает при каждой загрузке через
`ShProjectContext` из `project-context.php` модуля `shef.options`: есть Monolog
в vendor Composer проекта — своя копия не регистрируется, нет — регистрируется
`vendor/monolog/monolog/src/Monolog`. Файл `project-context.php` ищется
`Loader::getLocal()`, то есть и в `/local/modules`. Нет `shef.options`, упал
разбор `composer.json` проекта — своя копия: модулю без логгера хуже.

Путь модуля считается от корня сайта (`autoload.php` приклеивает к нему
`DOCUMENT_ROOT`); каталог — у `Loader::getLocal()`, а не у `__DIR__`, который
раскрывает симлинк `/local`. Модуль вне корня сайта — путь по умолчанию
`/bitrix/modules/shef.problems`.

**Свой namespace в `registerNamespace` не нужен:** ядро отображает
`Shef\Problems\Main\Utils` в `lib/main/utils.php` по соглашению, путь
строчными. Там только Monolog.

**`include.php`: `def-functions.php` первым, потом `autoload.php`.**
`autoload.php` подключает `shef.options`, а тот объявляет свои `_log()`,
`_log1()`, `_pr()` под `function_exists`. Порядок решает, чьи победят;
`tests/include_test.php` ловит перестановку. Сигнатура `_log(array, string)`
та же, что у shef.options: его трейт `TraitList\Log` зовёт `_log()` с двумя
аргументами, и работать обязана любая победившая версия.

**Сервисы-логгеры** — ключ `services` в `.settings.php`, по одному на случай
enum `\Shef\Problems\Logger`. `handlerType` сервиса решает, попадёт ли логгер в
меню «Логи» (`file`). `Logger::Problems` — не логгер, а фабрика
`Factory\SystemLoggerFactory`, её строят через трейт `Factory\Trait\LoggerProblems`.

**Каталог логов — вне корня сайта.** `Constants::getLogDir()`: по умолчанию
на уровень выше `DOCUMENT_ROOT` (`/home/bitrix/www` → `/home/bitrix/sh_log`),
либо абсолютный путь из `/bitrix/.settings_extra.php`, ключ `shef.problems` →
`logDir`. Путь к файлу — только через `getLogFullPath()`, строкой нигде.

**Меню** — `admin/menu.php`. Ядро само подключает этот файл для каждого
установленного модуля; регистрировать и копировать не нужно. Разметку строит
`Integration\Main\AdminMenu::build()` — чистый метод, без глобалов, чтобы
его можно было проверить без портала.

**Страница логов** — `admin/logs.php`, открывается заглушкой
`/bitrix/admin/shef_problems_logs.php`. Заглушка — одна строка `require`, как
принято в Битриксе, логики в ней нет. Её **пишет**, а не копирует
`Main\AdminPage`: путь — туда, где модуль стоит на самом деле
(`/bitrix/modules` или `/local/modules`, вне корня сайта — абсолютный).
Пишут установщик (`InstallFiles()`) и меню на портале, обновлённом заменой
файлов (`AdminMenu::ensureLogsPage()`). Удаляет — только свою: ту, что модуль
написал бы из своего каталога, или прежнюю его версию; чужой файл на этом
месте не трогают ни установка, ни удаление. Ссылка на страницу — в меню и на
вкладке «Сотрудники» страницы настроек (строка `Logs`, там же каталог логов).
Имя файла из запроса в путь превращает только `Main\LogFiles::resolve()`.

**Фронт** — `install/js/shef-problems/<имя>/`, копируется в `/bitrix/js`.
Имена расширений — только `Constants::EXTENSION_PR_HTML` и
`EXTENSION_PR_HTML_ADMIN`, строкой нигде больше; сходимость держит
`tests/assets_test.php`.

**Страница настроек** — `options.php` (копия канонической из shef.options) и
`options_conf.php`. Вкладка «Сотрудники» (`DEF`), вкладка «Зависимости»
собирается сама — `requireModules` непуст.

**Установщик** — `install/index.php`, класс `shef_problems`, каноническая
копия из shef.options плюс: `NEED_MODULES_BY_VERSION` = `shef.options` 3.0.0,
`getLegacyDirList()` (`/bitrix/images/shef.problems` от 1.x),
`getLegacyEventsList()` (обработчик `shef.uiclear` от 1.x), заглушка страницы
логов через `AdminPage`. Каталог модуля — `getModuleDir()` = `dirname(__DIR__)`,
а не зашитый `/bitrix/modules/<id>`: иначе модуль из `/local/modules` при
установке не копировал бы ничего. В каноническом установщике shef.options путь
зашит до сих пор. Ещё отличие: отказ «нужен `shef.*` версии N» ведёт на
релизы GitHub (`bx-shef/<имя без shef.>`), а не на Marketplace — там модуль
снят с публикации. Обе правки — к переносу в канон, bx-shef/options#28.
