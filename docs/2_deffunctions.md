# Быстрая отладка: `_pr`, `_log`, `_log1`

Функции объявляет `def-functions.php`, подключается он из `include.php`.

| функция | что делает |
|---|---|
| `_pr($o, bool $show = false)` | вывод на экран, по умолчанию только администратору; всё экранируется |
| `_log(array $value = [], string $fileName = 'log-custom')` | запись в `<каталог логов>/<fileName>.log`, дописыванием |
| `_log1(array $value = [], string $fileName = 'log1-custom')` | то же, но первый вызов за запрос файл перезаписывает |

К каждой записи добавляется трассировка — откуда позвали.

## Чьи функции победят

Те же три функции объявляет `shef.options`, и каждая закрыта
`function_exists`: побеждает тот, кто объявил первым.

1. **Версия проекта** — `bitrix/php_interface/def-functions.php`, если есть:
   её подключают оба модуля до своих.
2. **Этот модуль** — `include.php` подключает `def-functions.php` до
   `autoload.php`, а `autoload.php` уже подключает `shef.options`.
3. **shef.options** — если его подключили в запросе раньше этого модуля.

Сигнатура `_log()` у обоих модулей одна — массив и имя файла, — так что
вызов работает с любой. Разница в каталоге: этот модуль пишет в
каталог логов вне корня сайта (`Constants::getLogDir()`), `shef.options` — в
`/local/log`.

## Исключение в ошибку ядра

# [`\Shef\Problems\Throwable`] Обработка \Throwable

| класс | что делает |
|---|---|
| Manager::buildError | `\Throwable` → `\Bitrix\Main\Error`: текст, файл, строка и по желанию трассировка |
| Manager::traceToString | трассировка строкой, как `getTraceAsString()`, но без аргументов вызовов — в них бывают пароли |

```php
$result = new \Bitrix\Main\Result();

try
{
	// ...
}
catch(\Throwable $throwable)
{
	$result->addError(
		\Shef\Problems\Throwable\Manager::buildError(
			throwable: $throwable,
			isUseTrace: false,
			code: 'codeError',
			customData: [
				'key' => 'value'
			]
		)
	);
}
```

Запускаемый пример — [examples/throwable.php](../examples/throwable.php).

[← События](1_events.md) | [↑ Содержание](../README.md) | [Уровни логирования →](3_loglevel.md)
