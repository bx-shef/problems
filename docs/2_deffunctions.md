# Набор заглушек

| Функция | Описание                                      |
|--------:|:----------------------------------------------|
|     _pr | вывод на экран                                |
|    _log | вывод в файл лога                             |
|   _log1 | вывод в файл лога и автоочистка перед записью |


# [`\Shef\Problems\Throwable\Manager`] Обработка \Throwable
Позволяет исключение `\Throwable` перевести в `Bitrix\Main\Error`

Пример использования:
```php
<?php
$result = new \Bitrix\Main\Result();

try
{
  // ... ////
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
?>
```

[← События](docs/1_events.md) | [↑ Содержание](README.md) | [Уровни логирования →](docs/3_loglevel.md)