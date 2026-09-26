# shef.problems

Модуль Битрикс24 «коробки» и БУС для логирования и учёта проблем. Подключает
[Monolog](https://github.com/Seldaek/monolog) и даёт готовые логгеры: в файл, в
журнал событий Битрикса, на экран администратору. Проблемы разложены по типам —
общие, синхронизация, товары, продажи, — и у каждой есть ответственный из
настроек модуля.

Опирается на [shef.options](https://github.com/bx-shef/options): его нужно
поставить первым.

# Что нужно для установки

| | |
|---|---|
| PHP | 8.2 и выше |
| Главный модуль Битрикс | 22.600.300 и выше |
| Модуль `shef.options` | 3.0.0 и выше |
| Кодировка портала | **только UTF-8** |
| Расширение PHP | `mbstring` |

# Установка

**Порядок шагов важен:** сначала `shef.options`, потом файлы этого модуля, потом
установка в административном разделе, и только потом настройки.

## Через Composer

```bash
composer require bxshef/problems
```

Модуль развернётся в `bitrix/modules/shef.problems/` сам, вместе с ним приедут
`bxshef/options` и `monolog/monolog`. Composer 2.2+ требует разрешить плагин
раскладки — один раз, в `composer.json` проекта:

```json
{
	"config": {
		"allow-plugins": {
			"composer/installers": true
		}
	}
}
```

## Из архива

Скачайте `shef.problems.zip` со [страницы релизов](https://github.com/bx-shef/problems/releases)
и распакуйте в `bitrix/modules/`. Должно получиться
`bitrix/modules/shef.problems/` — именно через точку. Monolog лежит внутри
архива, отдельно его ставить не нужно.

## Откуда берётся Monolog

Из двух мест, и оба оставлены сознательно:

* есть Monolog в Composer проекта — модуль берёт его;
* нет — модуль подключает свою копию из `vendor/`.

Решает это модуль сам, при каждой загрузке. Composer проекта Битрикс видит,
если путь к `composer.json` указан в `/bitrix/.settings.php`, ключ `composer`.

## Дальше — в административном разделе

1. **Настройки → Marketplace → Установленные решения** → «[SH] Учёт проблем» →
   **Установить**.
2. **Настройки → Настройки продукта → Настройки модулей → [SH] Учёт проблем** →
   вкладка «Сотрудники»: кому уходят проблемы каждого типа. Не заполните —
   всё уйдёт пользователю с ID 1.
3. Логи пишутся **вне корня сайта** — на уровень выше него: при корне
   `/home/bitrix/www` это `/home/bitrix/sh_log`. Веб-сервер их не отдаёт,
   смотреть — через **Настройки → Учёт проблем → Логи**. Свой каталог, права,
   `open_basedir` и перенос логов 1.x —
   [безопасность логов](https://github.com/bx-shef/problems/blob/main/docs/security.md).
4. По желанию — [ротация логов](https://github.com/bx-shef/problems/blob/main/docs/5_logrotate.md).

# Как пользоваться

Проблема в своём классе — трейт, две строки настройки, одна строка записи:

```php
\Bitrix\Main\Loader::includeModule('shef.problems');

final class OrdersExchange
{
	use \Shef\Problems\Factory\Trait\LoggerProblems;

	public function __construct()
	{
		$this->initLogger();
	}

	public static function getClassName(): string { return static::class; }
	public static function getModuleId(): string { return 'acme.exchange'; }

	public function run(): void
	{
		$this->logger->error('1С не ответила', ['itemId' => 1024]);
	}
}
```

Запись ляжет в `sh_problems_problem.log` в каталоге логов и в журнал событий.
Отладка на экран администратору:

```php
\Shef\Problems\Logger::PrHtml->getLogger()->debug('что пришло', $fields);
```

Логгеру можно отдать не только строку, а исключение, `Result` или `Error`
ядра, массив — он сам разложит их по сообщению и контексту.

Логи и журнал — в меню административной части: **Настройки → Учёт проблем**.

# Документация

Вся документация — в репозитории:

* [события и меню](https://github.com/bx-shef/problems/blob/main/docs/1_events.md)
* [_pr, _log, _log1 и исключения](https://github.com/bx-shef/problems/blob/main/docs/2_deffunctions.md)
* [уровни логирования](https://github.com/bx-shef/problems/blob/main/docs/3_loglevel.md)
* [Monolog: логгеры, фабрика, свои настройки](https://github.com/bx-shef/problems/blob/main/docs/4_monolog.md)
* [ротация логов](https://github.com/bx-shef/problems/blob/main/docs/5_logrotate.md)
* [безопасность логов](https://github.com/bx-shef/problems/blob/main/docs/security.md)
* [запускаемые примеры](https://github.com/bx-shef/problems/blob/main/examples/README.md)
* [проверка на портале](https://github.com/bx-shef/problems/blob/main/docs/portal-check.md)
* [change log](https://github.com/bx-shef/problems/blob/main/CHANGELOG.md)

# Развитие

* робот для бизнес-процессов
* проблема — задачей
* проблема — письмом через Битрикс24
* проблема — в чат Битрикс24
* проблема — администратору через `CAdminNotify::Add`
* проблема — пользователю в верхнюю панель Битрикс24
* проблема — в Telegram

# Лицензия

[MIT](https://github.com/bx-shef/problems/blob/main/LICENSE)
