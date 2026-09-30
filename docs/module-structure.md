# Раскладка репозитория

Файл про устройство репозитория. Опорные точки модуля — в [CLAUDE.md](../CLAUDE.md),
процесс — в [CONTRIBUTING.md](../CONTRIBUTING.md), сборка — в
[build-and-install.md](build-and-install.md).

## Модуль лежит в корне, и это вынужденно

Composer разворачивает в целевой каталог **корень пакета целиком** и подкаталоги
выбирать не умеет. Поэтому `lib/`, `install/`, `lang/` лежат прямо в корне
репозитория, рядом с `build.sh` и `.github/`.

Плата за это — два списка в шапке `build.sh`:

* **SHIP** — уезжает на портал и в Composer-пакет;
* **KEEP** — остаётся в репозитории.

**Файл, не попавший ни в один список, роняет сборку.** Тот же список продублирован
в `.gitattributes` через `export-ignore`; списки обязаны совпадать, сверяется
автоматически, см. `check_gitattributes`.

## Что где лежит

| путь | | что это |
|---|---|---|
| `install/index.php` | SHIP | установщик, класс `shef_problems extends CModule` |
| `install/version.php` | SHIP | `VERSION` и `VERSION_DATE` — источник истины о версии |
| `install/js/shef-problems/` | SHIP | стили вывода `PrHtml`; установщик раскладывает их в `/bitrix/js` |
| `admin/menu.php` | SHIP | меню «Учёт проблем»; ядро подключает его само, из каталога модуля |
| `admin/logs.php` | SHIP | страница просмотра логов; открывается заглушкой `/bitrix/admin/shef_problems_logs.php`, которую пишет установщик (`Main\AdminPage`) |
| `.settings.php` | SHIP | зависимости, события, раскладка, сервисы-логгеры, откуда брать Monolog |
| `include.php` | SHIP | точка входа: `def-functions.php`, потом `autoload.php` — порядок важен |
| `autoload.php` | SHIP | подключает `shef.options` и регистрирует Monolog |
| `def-functions.php` | SHIP | `_pr()`, `_log()`, `_log1()` |
| `default_option.php` | SHIP | умолчания настроек |
| `options.php`, `options_conf.php` | SHIP | страница настроек на `ShOptionsConfig` из `shef.options` |
| `lib/` | SHIP | классы модуля, **имена файлов строго строчными** |
| `lang/ru/` | SHIP | языковые файлы, зеркалят структуру `lib/` |
| `vendor/monolog/monolog/` | SHIP | своя копия Monolog для установки архивом |
| `README.md`, `CHANGELOG.md`, `LICENSE` | SHIP | |
| `composer.json` | SHIP | манифест пакета `bxshef/problems` |
| `docs/` | KEEP | вся документация, пример настроек logrotate |
| `build.sh` | KEEP | сборка и проверки |
| `tests/` | KEEP | тесты и заглушки ядра |
| `examples/` | KEEP | запускаемые примеры |
| `.claude/skills/` | KEEP | навыки агента — **копия** из `bx-shef/options`, раскладывает `sync.sh` |
| `.github/` | KEEP | CI и релиз |
| `CONTRIBUTING.md`, `CLAUDE.md` | KEEP | процесс и памятка агенту |
| `.gitattributes`, `.gitignore` | KEEP | |
| `.php-cs-fixer.dist.php` | KEEP | правила линтера, `@PSR12` |
| `composer.lock` | KEEP | держит зависимости линтера; пакету не нужен — Composer читает lock только у корневого проекта |
| `vendor-dev/` | — | инструменты разработчика из `composer install`, в `.gitignore` |

## Нижний регистр в `lib/` обязателен

`Bitrix\Main\Loader` отображает класс в путь **строчными**, разбирая первые два
сегмента namespace как id модуля: `Shef\Problems\Main\Utils` ищется как
`bitrix/modules/shef.problems/lib/main/utils.php`. Поэтому свой namespace в
`registerNamespace` не нужен — там только Monolog. Отсюда же и трейты в
`lib/factory/trait/`: сегмент `Trait` в namespace PHP 8 принимает.

На macOS заглавная буква сходит с рук, на боевом Linux класс просто не найдётся.
Проверяется в `build.sh`, `check_lowercase`, и в `tests/autoload_test.php`.

У `vendor/` соглашение своё — PSR-4 с заглавными, путь задаёт `.settings.php`.

## Фронт

```
install/js/shef-problems/monolog-pr-html/        -> /bitrix/js/shef-problems/monolog-pr-html/
install/js/shef-problems/monolog-pr-html-admin/  -> /bitrix/js/shef-problems/monolog-pr-html-admin/
```

Каталог модуля браузеру недоступен, поэтому стили копирует установщик — карта
в `.settings.php`, ключ `installDir`. Расширения находятся ядром по имени
`shef-problems.monolog-pr-html`: каталог через дефис — требование имён
расширений. Имена живут в одном месте, `Constants::EXTENSION_PR_HTML` и
`EXTENSION_PR_HTML_ADMIN`; сходимость с раскладкой проверяет
`tests/assets_test.php`.

`style.min.css` рядом со `style.css` — минифицированная копия, её ядро берёт
при включённой оптимизации css. Правите стиль — пересоберите и её.
`*.min.min.*` — мусор сборщиков, его отсекает `.gitignore`.

## Документация не едет на портал

Документация живёт в репозитории. В поставке остаётся только `README.md` — как
readme пакета, — и все ссылки из него ведут на GitHub. Скриншоты, которые до
2.0.0 раскладывались в `/bitrix/images/shef.problems`, ушли вместе с
документацией; каталог на обновлённых порталах убирает деинсталляция.
