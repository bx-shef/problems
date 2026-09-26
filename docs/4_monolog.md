# Monolog

## Почитать
* [monolog](https://github.com/Seldaek/monolog)
* [Логирование в распределенном php-приложении](https://habr.com/ru/post/456676/)
* [Ведение журнала с PSR-3 для улучшения возможности повторного использования](https://coderlessons.com/articles/php/vedenie-zhurnala-s-psr-3-dlia-uluchsheniia-vozmozhnosti-povtornogo-ispolzovaniia)

## Предустановленные логгеры
Реализованы через сервисы:

|                                Сервис | Уровень логирования | Назначение                                                                                      |
|--------------------------------------:|:--------------------|:------------------------------------------------------------------------------------------------|
|              `shef.problems.pr.debug` | Level::Debug        | для вывода не отворматированного текста, в агента и тп использовать                             |
|          `shef.problems.prHtml.debug` | Level::Debug        | для вывода отворматированного текста                                                            |
|             `shef.problems.log.debug` | Level::Debug        | запись в лог отладки                                                                            |
|            `shef.problems.log1.debug` | Level::Debug        | запись в лог отладки, при первом использовании файл будет удален и создан повторно              |
|    `shef.problems.deprecations.alert` | Level::Alert        | запись в лог устаревания функций, которые будут удалены в будущей версии                        |
| `shef.problems.factory.system.logger` | xx                  | фабрика создания логгера записи события в [\CEventLog](/bitrix/admin/event_log.php) и файл лога |


Пример подключения
```php
<?php
  \Bitrix\Main\Loader::includeModule('shef.problems');
  $logger = \Bitrix\Main\DI\ServiceLocator::getInstance()->get('shef.problems.prHtml.debug');
  $logger->debug('Test debug');
  $logger->info('Test info');
  $logger->error('Test error');
?>
```

### Подключение через фабрику создания логгера записи события `shef.problems.factory.system.logger`
```php
<?php
  /** @var \Shef\Problems\Factory\SystemLoggerFactory $factory */
  $factory = \Bitrix\Main\DI\ServiceLocator::getInstance()->get('shef.problems.factory.system.logger');
  $logger = $factory::build(
    logLevel: \Monolog\Level::Alert,
    auditType: \Shef\Problems\Main\Constants::AuditTypeSync,
    moduleId: 'shef.problems',
    className: 'Test',
    assigned: \Shef\Problems\Main\Constants::getAdminId(),
  );
  
  $logger->debug('Не работает, тк в указали уровень Alert', ['_shef.problems.build.logger']);
  $logger->alert('Работает', ['_shef.problems.build.logger']);
?>
```

### Использование через enum [`\Shef\Problems\Logger`] 
```php
<?php
  \Bitrix\Main\Loader::includeModule('shef.problems');
  \Shef\Problems\Logger::PrHtml->getLogger->debug('message', ['context']);
?>
```

### Использование через `\Bitrix\Main\Diag\Logger::create`
Вначале определяем в `\bitrix\.settings.php` или в `\bitrix\.settings_extra.php` ключ `'loggers'`
```php
<?php
return [
  'loggers' => [
    'value' => [
      'shef.problems.prHtml' => [
        'constructor' => static function () {
          if(in_array(
            \Bitrix\Main\Loader::includeSharewareModule('shef.problems'),
            [
              \Bitrix\Main\Loader::MODULE_INSTALLED,
              \Bitrix\Main\Loader::MODULE_DEMO,
            ]
          ))
          {
            return \Shef\Problems\Logger::PrHtml->getLogger();
          }
          
          return null;
        },
      ],
      'shef.problems.pr' => [
        'constructor' => static function () {
          if(in_array(
            \Bitrix\Main\Loader::includeSharewareModule('shef.problems'),
            [
              \Bitrix\Main\Loader::MODULE_INSTALLED,
              \Bitrix\Main\Loader::MODULE_DEMO,
            ]
          ))
          {
            return \Shef\Problems\Logger::Pr->getLogger();
          }
          
          return null;
        },
      ],
      'shef.problems.log' => [
        'constructor' => static function () {
          if(in_array(
            \Bitrix\Main\Loader::includeSharewareModule('shef.problems'),
            [
              \Bitrix\Main\Loader::MODULE_INSTALLED,
              \Bitrix\Main\Loader::MODULE_DEMO,
            ]
          ))
          {
            return \Shef\Problems\Logger::Log->getLogger();
          }
          
          return null;
        },
      ],
      'shef.problems.log1' => [
        'constructor' => static function () {
          if(in_array(
            \Bitrix\Main\Loader::includeSharewareModule('shef.problems'),
            [
              \Bitrix\Main\Loader::MODULE_INSTALLED,
              \Bitrix\Main\Loader::MODULE_DEMO,
            ]
          ))
          {
            return \Shef\Problems\Logger::Log1->getLogger();
          }
          
          return null;
        },
      ],
    ],
    'readonly' => true
  ]
];
?>
```

Теперь можем вызывать через `\Bitrix\Main\Diag\Logger::create`

Пример
```php
<?php
  $logger = \Bitrix\Main\Diag\Logger::create('shef.problems.prHtml');
  $logger?->debug('Diag\Logger message', ['_prHtml']);
?>
```

### Собственные настройки
Вначале определяем в `\bitrix\.settings.php` или в `\bitrix\.settings_extra.php` ключ `'loggers'`

В примере указан логгер `test` с обработчиками:

* запись событий уровня `\Monolog\Level::Debug` в файл `/local/log/test.log`
* запись событий уровня `\Monolog\Level::Info` в файл Telegram (apiKey и channel нужно свои подставить)

```php
<?php
return [
  'loggers' => [
    'value' => [
      'test' => [
        'constructor' => static function () {
          if(in_array(
            \Bitrix\Main\Loader::includeSharewareModule('shef.problems'),
            [
              \Bitrix\Main\Loader::MODULE_INSTALLED,
              \Bitrix\Main\Loader::MODULE_DEMO,
            ]
          ))
          {
            return (new \Shef\Problems\Integration\Monolog\Logger('test'))
              ->pushHandler(
                (new \Monolog\Handler\StreamHandler(
                  stream: \Bitrix\Main\Application::getDocumentRoot().'/local/log/test.log',
                  level: \Monolog\Level::Debug
                ))
              )
              ->pushHandler(
                (new \Monolog\Handler\TelegramBotHandler(
                  apiKey: '1848526049:UNasdasd_DEMO',
                  channel: '-300000001',
                  level: \Monolog\Level::Info
                ))
              )
            ;
          }
          
          return null;
        },
      ],
    ],
    'readonly' => true
  ]
];
?>
```

Теперь можем вызывать через `\Bitrix\Main\Diag\Logger::create`

Пример
```php
<?php
  $logger = \Bitrix\Main\Diag\Logger::create('test');
  $logger?->debug('Запишет в файл', ['в телеграм НЕ ОТПРАВИТ']);
  $logger?->info('Запишет в файл', ['в телеграм ОТПРАВИТ']);
?>
```

## Обработчики
* все из [monolog.handlers](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#handlers)
* доработанные [`\Shef\Problems\Integration\Monolog\Handler`]

|                         Название | Описание                                                                                                                                                                                       |
|---------------------------------:|:-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Handler\BitrixCEventLogHandler` | для записи в [\CEventLog](/bitrix/admin/event_log.php).<br/>Настройка очистка журнала в главном модуле.<br/>Можно добавить [оповещения в админке Б24](/bitrix/admin/log_notification_edit.php) |
|              `Handler\PrHandler` | для вывода на экран                                                                                                                                                                            |
|          `Handler\PrHtmlHandler` | для HTML вывода на экран                                                                                                                                                                       |
|            `Handler\Log1Handler` | для записи в файл с предварительной очисткой файла                                                                                                                                             |

## Логгер [`Shef\Problems\Integration\Monolog\Logger`]
> Унаследован от `\Monolog\Logger`

Разрешает для функций логирования первым параметром предать следующие типы:

* \Throwable
* \Bitrix\Main\Result
* \Bitrix\Main\Error
* array
* \Bitrix\Main\Type\Contract\Arrayable
* \Bitrix\Main\Type\Contract\Jsonable
* \JsonSerializable
* \Stringable
* string

Через соответствующие стратегии `Shef\Problems\Integration\Monolog\Strategy\LoggerConverter\IStrategy` заворачивает в коректный формат

[← Уровни логирования](docs/3_loglevel.md) | [↑ Содержание](README.md) | [Ротация логов →](docs/5_logrotate.md)