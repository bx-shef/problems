# Памятка агенту: shef.problems

Модуль Битрикс24 «коробки» и БУС для логирования и учёта проблем на Monolog:
файлы логов, журнал событий Битрикса, вывод отладки администратору. Опирается
на `shef.options` и сделан по его канону — сборка, тесты, раскладка, решения,
ловушки. Если здесь чего-то не хватает, смотрите
[CLAUDE.md shef.options](https://github.com/bx-shef/options/blob/main/CLAUDE.md):
всё, что там сказано про устройство модуля линейки, верно и здесь.

Проверка перед сдачей — всегда `./build.sh --check`, ровно это же гоняет CI.

Тесты лежат в `tests/`, гоняются как обычные скрипты. Обвязка
`tests/assert.php` общая с shef.options: сравнение строгое, warning и notice —
провал. Ядро подменяется заглушками из `tests/stub/bitrix.php`, интерфейсы
PSR-3 — `tests/stub/psr.php`, классы модуля и своя копия Monolog подключаются
**настоящие** автозагрузкой `tests/stub/autoload.php` — по тому же соглашению,
что на портале.

| тест | что держит |
|---|---|
| `autoload_test.php` | класс лежит там, где его ищет ядро; один FQCN — один файл |
| `settings_test.php` | `.settings.php`: Monolog находится, обработчики событий и сервисы существуют, раскладка не висит |
| `assets_test.php` | имена расширений фронта сходятся с раскладкой установщика |
| `trace_test.php` | трассировка без warning и с первой строкой там, где позвали логгер |
| `prhandler_test.php` | вывод на экран — только администратору и экранированным |
| `eventlog_test.php` | журнал событий: важность, тип, модуль, элемент; фабрика проблем |
| `logger_test.php` | что логгер делает с исключением, `Result`, `Error`, массивом; enum `Logger` |
| `constants_test.php` | строгий разбор ID сотрудников из настроек |
| `adminmenu_test.php` | меню «Учёт проблем»: только администратору, логи через fileman |
| `include_test.php` | подключение модуля даёт свои `_log()`, `_log1()`, `_pr()` |
| `uninstall_test.php` | удаление уносит настройки и обработчики, включая оставшийся от 1.x |
| `vendor_test.php` | своя копия Monolog подходит под `composer.json` и не даёт deprecation |
| `composer_test.php` | тип пакета, `installer-name`, потолок `composer/installers` |
| `docs_test.php` | ссылки в `*.md` живые, классы и методы из документации существуют |
| `examples_test.php` | каждый пример из `examples/` запускается и сходится с обещанным |
| `skills_test.php` | навыки оформлены, копия сходится со своим `MANIFEST` |
| `evals_test.php` | evals навыков разбираются |

**Навыки — копия, а не источник.** `.claude/skills/` раскладывает
`sync.sh --to` из [bx-shef/options](https://github.com/bx-shef/options); там
же их и правят. Навык про этот модуль — `shef-use-logger` — тоже живёт в
shef.options: навыки — один каталог на всю линейку. Класс
`\Shef\Problems\...`, названный в навыке, проверяет **этот** репозиторий
(`tests/docs_test.php`), а не источник. Задача `Skills` в CI сверяет копию с
источником и краснеет, когда источник ушёл вперёд — это сигнал разложить
заново, а не чинить копию.

**Примеры в `examples/` запускаются, а не читаются.** Как в shef.options:
`php examples/x.php` — на заглушках, `DOCUMENT_ROOT=/var/www/portal php
examples/x.php` — на живом портале. Шапка — ЦЕЛЬ, ГДЕ ПРИМЕНЯТЬ, ЧТО ДОЛЖНО
ПОЛУЧИТЬСЯ, ЗАПУСК.

## Опорные точки

**Monolog — из двух мест.** `.settings.php` решает при каждой загрузке через
`ShProjectContext` из `project-context.php` модуля `shef.options`: есть Monolog
в vendor Composer проекта — своя копия не регистрируется, нет — регистрируется
`vendor/monolog/monolog/src/Monolog`. Файл `project-context.php` ищется
`Loader::getLocal()`, то есть и в `/local/modules`. Нет `shef.options`, упал
разбор `composer.json` проекта — своя копия: модулю без логгера хуже.

Путь модуля считается от корня сайта (`autoload.php` приклеивает к нему
`DOCUMENT_ROOT`); модуль вне корня сайта — путь по умолчанию
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

**Меню** — `admin/menu.php`. Ядро само подключает этот файл для каждого
установленного модуля; регистрировать и копировать не нужно. Разметку строит
`Integration\Main\AdminMenu::build()` — чистый метод, без глобалов, чтобы
его можно было проверить без портала.

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
`getLegacyEventsList()` (обработчик `shef.uiclear` от 1.x).

## Решения владельца

Это решения, а не баги. Не переигрывать без просьбы.

**Monolog: и Composer, и своя копия.** Оба пути поставки остаются: Composer
кладёт Monolog в vendor проекта, архив несёт копию в `vendor/`. Какой
подключать — решает `ShComposerContext` из shef.options. Копия обязана
подходить под ограничение из `composer.json` — `tests/vendor_test.php`.

**shef.uiclear не используем — штатные возможности Битрикс24.** Пункты «Логи»
и «Журналы» с верхней панели (событие `shef.uiclear`) заменены меню
административной части. Класс `Integration\Shef\UiClear\Events` — заглушка
для порталов, обновлённых с 1.x, у которых регистрация обработчика осталась;
удалять со следующей мажорной версией.

**Пакет Composer — `bxshef/problems`, тип `bitrix-module`**, `installer-name =
shef.problems`, лицензия MIT, потолок `composer/installers`. Всё — ровно как в
shef.options и по тем же причинам (см. его CLAUDE.md, «bitrix-d7-module
развернул бы модуль не туда»). В 1.1.7 стояло `shef/problems` +
`bitrix-d7-module`: модуль уехал бы в `bitrix/modules/shef.shef.problems/`.

**Модуль поставляется только в UTF-8**, **настройки уходят вместе с модулем**
(`savedata = Y` оставляет) — как в shef.options.

**Документация живёт в репозитории.** В поставке — только README, ссылки из
него на GitHub. Скриншоты в `/bitrix/images` больше не раскладываются, пример
logrotate — в `docs/logrotate/`.

**База — сборка 1.1.7** (импорт как есть — первый коммит после инициализации).
Владелец пришлёт ещё версии; конечную собирать из них, сверяя с этой
адаптацией.

## Ловушки, за которые заплачено

**Monolog 3.3.1 сыпал deprecation на PHP 8.4** — «Implicitly marking
parameter as nullable» прямо из `Monolog\Logger`, то есть в лог портала на
каждый запрос с логгером. Своя копия обновлена до 3.12.0, ограничение в
`composer.json` — `^3.10` (с 3.10 исправлены и deprecation PHP 8.5).
`tests/vendor_test.php` разбирает каждый файл копии с `error_reporting=-1` на
каждой версии PHP из матрицы CI.

**`ShOptionsConfig::getInstance(indexDoc: ...)` — Error в shef.options 3.x.**
Параметр ушёл вместе с вкладкой «Документация», а именованный аргумент,
которого нет, — это «Unknown named parameter», то есть неоткрывающаяся
страница настроек. Ни `php -l`, ни тест без портала этого не видят —
шаг B в [docs/portal-check.md](docs/portal-check.md).

**Кадр трассировки без ключей.** У кадра встроенной функции нет `file` и
`line`, у вызова функции — `class` и `type`. `TraceRow\Row` читал их прямо:
warning на каждую запись с трассировкой.

**Трассировку режут по файлам, а не по счёту.** Было `array_slice(..., 6)` —
глубина до процессора на обработчике. Процессор на логгере или вызов через
`log()` — глубина другая. Теперь отрезаются кадры, вызванные из каталога
Monolog (берётся у настоящего класса — Composer или своя копия) и из
`lib/integration/monolog/`.

**Вывод на экран — это HTML.** `PrHandler`, `PrHtmlHandler`, `_pr()` печатают
данные записи в страницу администратора; без экранирования — хранимый XSS.

**Журнал событий знает пять важностей:** SECURITY, ERROR, WARNING, INFO,
DEBUG. Остальное `CEventLog::Add()` пишет как UNKNOWN — и CRITICAL/ALERT/
EMERGENCY становились «неизвестными». Сопоставление —
`BitrixCEventLogEntity::mapSeverity()`, исходный уровень остаётся в описании.

**Каталог логов под корнем сайта.** `/local/sh_log` не закрыт в стандартной
поставке. Прямые ссылки на `*.log` из меню работали ровно тогда, когда каталог
открыт всем; теперь меню ведёт через `fileman_file_view.php`, а закрыть каталог
— забота проекта, [docs/security.md](docs/security.md).

**Регистрация обработчика переживает замену файлов.** Убрали событие из
`installEvents` — на обновлённом портале оно осталось в `b_module_to_module` и
зовёт класс. Поэтому класс остаётся заглушкой, а деинсталляция снимает и
старую регистрацию (`getLegacyEventsList()`).

**`d_size` — не директива logrotate.** Строка в примере настроек молча
игнорировалась, порога по размеру не было. Теперь `maxsize`.

**`intval()` не годится для ID из настроек** — как в shef.options:
`Constants::getIdOption()` принимает только целое > 0 или строку из одних цифр.

**Проверяй артефакт, а не свой диф** — как в shef.options.

## Известные шероховатости

Не трогать заодно — отдельной задачей, с проверкой на портале.

* Вкладка `GRP` в `options_conf.php` выключена комментарием-переключателем, а
  `Constants::getGroupIdTask()` и `getGroupIdIntegrateB24()` читают её опции и
  всегда отдают умолчание. Публичный API, удалять вслепую нельзя; решить, когда
  придут следующие версии модуля.
* `Logger::getEnumUsedInterface()` называется не по делу (фильтрует по типу
  обработчика, а не по интерфейсу) — публичный API, переименование отдельно.
* `TraceProcessor` в режиме `ModeBr` склеивает трассировку через `<br>`
  строкой, а `PrHandler` экранирует строку целиком — `<br>` видны текстом.
  Сервисы модуля этот режим не используют.
* Своя копия Monolog проверяется только по пути: если в vendor проекта лежит
  Monolog 2.x, модуль не станет регистрировать свою копию и сломается на
  `Monolog\Level`. Ставите модуль архивом на проект с Composer — проверьте
  версию Monolog в проекте (шаг F в portal-check).
* `Utils::workDateAdd()` и `getUserBoss()` к логированию отношения не имеют и
  тестами не покрыты: нужны `bizproc`, `iblock`, `intranet`.

## Приёмочный чек-лист

Процедура — [docs/portal-check.md](docs/portal-check.md): шаги с ожидаемым
результатом и бланк. Проходить **перед сдачей** и **на отдельном стенде**.

Главный сценарий для 2.0.0 — обновление со стенда с 1.x: страница настроек
открывается с `shef.options` 3.x, сотрудники на месте, `shef.uiclear` не
падает, меню «Учёт проблем» на месте, каталог логов закрыт.
