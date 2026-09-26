# Проверка на портале

Всё, что ниже рантайма Битрикса, тестами не закрыть: установка, права, меню,
раскладка файлов, журнал событий, поведение при обновлении. Проверять это
приходится руками — и лучше по списку, потому что забытый шаг находит не
разработчик, а клиент.

Процедура рассчитана на **отдельный стенд**, а не на боевой портал. Шаги
«удалить модуль» и «поставить на CP1251» на рабочем портале делать нельзя.

Раскладка репозитория — в [module-structure.md](module-structure.md), сборка —
в [build-and-install.md](build-and-install.md).

## Что понадобится

| | |
|---|---|
| портал | «коробка» Битрикс24 или БУС, главный модуль **22.600.300** и выше |
| PHP | **8.2** и выше, расширение `mbstring` |
| кодировка | **только UTF-8** |
| `shef.options` | **3.0.0** и выше, установлен |
| доступ | администратор портала и доступ к файлам по ssh |
| архив | со страницы релиза либо собранный `./build.sh` |

Для сценария «обновление» нужен стенд, где уже стоит **1.x** — на нём
проверяется то, ради чего 2.0.0 сделана мажорной.

## Перед началом

Снимите копию каталога модуля и настроек — шаги с удалением необратимы:

```bash
cp -a /var/www/portal/bitrix/modules/shef.problems /tmp/shef.problems.before 2>/dev/null
mysqldump -u… portal b_option --where="MODULE_ID='shef.problems'" > /tmp/opt.before.sql
mysqldump -u… portal b_module_to_module --where="TO_MODULE_ID='shef.problems'" > /tmp/events.before.sql
ls /var/www/portal/bitrix/js/ /var/www/portal/bitrix/images/ | sort > /tmp/public.before
```

## 0. Архив — тот самый

Архив собирается **побайтово одинаково** у всех, кто взял тот же коммит:

```bash
git clone https://github.com/bx-shef/problems.git
cd problems && git checkout <тег проверяемой версии>
./build.sh                       # последняя строка напечатает sha256
sha256sum /путь/к/скачанному/shef.problems.zip
```

Хеши обязаны совпасть. Первым уровнем внутри архива — ровно `shef.problems/`:

```bash
unzip -Z1 shef.problems.zip | cut -d/ -f1 | sort -u
```

## A. Чистая установка

1. Убедиться, что `shef.options` стоит и его версия 3.0.0 или выше.
2. Распаковать в `bitrix/modules/`, чтобы получилось `bitrix/modules/shef.problems/`.
3. **Marketplace → Установленные решения** → «[SH] Учёт проблем» → установить.

**Ожидается:** «Модуль успешно установлен»; в `/bitrix/js/shef-problems/`
появились два каталога — `monolog-pr-html` и `monolog-pr-html-admin`; появился
`/bitrix/admin/shef_problems_logs.php` — одна строка `require` на
`admin/logs.php` модуля **там, где модуль стоит** (поставили в
`/local/modules` — путь `/local/modules/…`); `/bitrix/images/shef.problems` **не**
появился.

**Отдельно:** на стенде без `shef.options` (или со старым 2.x) установка
обязана отказать с текстом про `shef.options` и версию — а не поставиться и
упасть на первой странице.

## B. Обновление с 1.x — главный сценарий 2.0.0

Делается на стенде, где стоит 1.x и настроены сотрудники.

1. Запомнить, что было:

```bash
ls /var/www/portal/bitrix/images/shef.problems/ 2>/dev/null    # в 1.x есть
```

2. Заменить каталог модуля содержимым новой версии **целиком** (старый убрать,
   новый распаковать): в 1.x в корне модуля лежали файлы, которых больше нет.
3. Открыть `/bitrix/admin/settings.php?mid=shef.problems`.

**Ожидается:**

* страница открывается. В 1.x `options_conf.php` передавал `indexDoc`, и с
  `shef.options` 3.x страница падала бы с «Unknown named parameter» — это и
  проверяем;
* вкладки «Сотрудники» и «Зависимости»; ранее выбранные сотрудники **на
  месте** — имена настроек не менялись;
* в логе портала нет «class not found» и deprecation от Monolog;
* если на стенде стоит `shef.uiclear` — в верхней панели пропали пункты
  «[SH] Логи» и «[SH] Журналы», и **ошибок нет**: обработчик 1.x отвечает
  заглушкой. Пункты теперь в меню административной части, шаг D.

* после первого открытия административной части появился
  `/bitrix/admin/shef_problems_logs.php`: установщик при замене файлов не
  запускался, страницу логов кладёт меню (`AdminMenu::ensureLogsPage()`);
* логи теперь пишутся вне корня сайта — старый `/local/sh_log` остался и
  **открыт** веб-серверу: перенесите логи и удалите его, см.
  [security.md](security.md), «После обновления с 1.x». Пути в logrotate — тоже.

**`/bitrix/images/shef.problems` после обновления останется** — это нормально:
убирает его деинсталляция, шаг H.

## C. Страница настроек

1. Выбрать на вкладке «Сотрудники» разных людей на каждую роль, сохранить.
2. Проверить из CLI, что каждая роль читается своя:

```bash
php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/portal"; define("NO_KEEP_STATISTIC",true); define("NOT_CHECK_PERMISSIONS",true);
require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";
\Bitrix\Main\Loader::includeModule("shef.problems");
foreach(["getDefUserId","getAdminId","getDirectorId","getSyncUserId","getProductsUserId","getSaleUserId"] as $m) echo $m, " = ", \Shef\Problems\Main\Constants::$m(), PHP_EOL;'
```

**Ожидается:** шесть строк, ID — ровно те, что выбрали.

На вкладке «Сотрудники» — строка «Логи модуля» со ссылкой и каталогом логов:
ссылка открывает `/bitrix/admin/shef_problems_logs.php`, каталог — вне корня
сайта (на BitrixVM `/home/bitrix/sh_log`).

## D. Меню «Учёт проблем»

1. Под администратором: **Настройки → Учёт проблем**.
2. Под пользователем с доступом в админку, но не администратором — то же.

**Ожидается:**

* администратору — раздел с группами «Логи», «Журнал событий» и пунктом
  «Настройки модуля»; не администратору — раздела нет;
* «Логи → Все логи» открывает `/bitrix/admin/shef_problems_logs.php`: строка
  «Каталог логов: …» — **вне корня сайта** (на BitrixVM `/home/bitrix/sh_log`),
  ниже список файлов; до шага E он может быть пуст;
* пункт отдельного лога открывает конец файла; файла ещё нет — страница
  скажет «файла нет», это нормально до шага E;
* не администратор, открывший `/bitrix/admin/shef_problems_logs.php` напрямую,
  получает форму входа, а не лог;
* `…/shef_problems_logs.php?file=../www/bitrix/.settings.php` — «файла нет», а
  не содержимое настроек;
* пункт журнала открывает журнал событий, отфильтрованный по типу;
* «Ошибки платёжных систем» есть, только если стоит `perfmon`.

## E. Запись проблемы

```bash
cd problems && DOCUMENT_ROOT=/var/www/portal php examples/problems.php
```

**Ожидается:** все строки `ok`, последняя — `ГОТОВО: problems`, первая строка
заканчивается на `[портал]`. После этого:

* в журнале событий запись типа `SH_PROBLEMS_SYNC`, модуль `acme.exchange`,
  элемент `1024`, **важность ERROR** (не UNKNOWN — запись уровня CRITICAL);
* в меню «Учёт проблем → Логи → [Monolog] Sh_problems_sync» открывается файл с
  этой записью;
* файл лежит **вне корня сайта**:

```bash
ls -l /home/bitrix/sh_log/sh_problems_sync.log     # есть, владелец — пользователь PHP
ls /home/bitrix/www/local/sh_log/ 2>/dev/null      # нового файла тут нет
```

Файла нет, а пример прошёл — смотрите лог PHP: `open_basedir` или права на
родительский каталог, см. [security.md](security.md). На стенде, обновлённом с
1.x, старый `/local/sh_log` остаётся и **открыт** веб-серверу — перенесите логи
и удалите его, там же.

## F. Откуда взят Monolog

```bash
php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/portal"; define("NO_KEEP_STATISTIC",true); define("NOT_CHECK_PERMISSIONS",true);
require $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";
\Bitrix\Main\Loader::includeModule("shef.problems");
echo (new ReflectionClass(\Monolog\Logger::class))->getFileName(), PHP_EOL;'
```

**Ожидается:**

* на портале без Composer (или без Monolog в нём) — путь внутри
  `bitrix/modules/shef.problems/vendor/`;
* на портале, где Monolog стоит через Composer проекта и путь к
  `composer.json` указан в `/bitrix/.settings.php` (ключ `composer`), — путь
  внутри vendor проекта.

## G. Вывод на экран

В административной части, под администратором, выполнить в «Командной PHP-строке»:

```php
\Bitrix\Main\Loader::includeModule('shef.problems');
\Shef\Problems\Logger::PrHtml->getLogger()->warning('<b>не жирный</b>', ['a' => 1]);
```

**Ожидается:** цветной блок (жёлтый — WARNING), трассировка слева или сверху;
текст `<b>не жирный</b>` виден **как текст**, а не жирным — вывод экранирован.
Нет цвета — не подключились стили: проверьте `/bitrix/js/shef-problems/`
и сбросьте кеш (Ctrl+F5).

## H. Удаление

1. **Marketplace → Установленные решения** → «[SH] Учёт проблем» → удалить.

**Ожидается:**

* `/bitrix/js/shef-problems/` и `/bitrix/images/shef.problems/` удалены;
* настроек модуля в `b_option` нет, настройки `shef.options` — на месте;
* в `b_module_to_module` не осталось обработчиков с `TO_MODULE_ID='shef.problems'`
  — в том числе обработчика `shef.uiclear` из 1.x;
* `/bitrix/admin/shef_problems_logs.php` удалён, остальные файлы
  `/bitrix/admin/` на месте. Если перед удалением положить на место заглушки
  свой файл — он остаётся;
* файлы в каталоге логов **остались**: логи — данные проекта, модуль их не
  трогает.

Если модуль зависит от других (`shef.*` с `shef.problems` в `requireModules`),
удаление обязано отказать и назвать их.

## I. Портал в CP1251

Установка обязана отказать с текстом про UTF-8. На современных ядрах ветка
недостижима — `Application::isUtfMode()` возвращает `true` без условий, — тогда
в бланке отмечается «пропущено», и это верный ответ.

## J. Примеры на живом ядре

```bash
for e in problems logger throwable log1; do DOCUMENT_ROOT=/var/www/portal php examples/$e.php || echo "FAIL $e"; done
```

**Ожидается:** четыре раза `ГОТОВО: …`, ни одного `FAIL`, `Warning`,
`Deprecated`.

## Бланк результата

```
Версия: ____  Коммит: ____  sha256 архива сошёлся: да / нет
Ядро main: ____  PHP: ____  shef.options: ____  Composer в проекте: да / нет
open_basedir: нет / есть, каталог логов в нём: да / нет

0. Архив .................................. ок / не ок
A. Чистая установка ....................... ок / не ок
   без shef.options — отказ ............... ок / не ок
B. Обновление с 1.x ....................... ок / не ок / нет стенда
C. Страница настроек ...................... ок / не ок
D. Меню «Учёт проблем» .................... ок / не ок
E. Запись проблемы ........................ ок / не ок
   лог вне корня сайта, путь: ____________ да / нет
F. Monolog взят из ....................... модуль / Composer
G. Вывод на экран экранирован ............. ок / не ок
H. Удаление ............................... ок / не ок
I. CP1251 ................................. ок / пропущено
J. Примеры на живом ядре .................. ок / не ок

Замечания:
```
