# shef.problems change log

## 1.1.8 — XXXX-XX-XX
* -

## 1.1.7 — 2023-09-20
* оптимизация кода

## 1.1.5 — 2023-09-20
* переделана структура с учетом публикации в МП
* рефакторинг

## 1.1.4 — 2023-03-28
* Настроен вывод PrHtml
* Добавлен Logger::Deprecations
* Добавлен декоратор \Shef\Problems\Integration\Monolog\Logger
* Добавлен файл тестирования
* Убраны Logger::Site, Logger::Onec - все через Logger::Problems теперь нужно делать, используя ```\Shef\Problems\Factory\Trait\LoggerProblems```
* Добавлена трасировка `\Shef\Problems\Integration\Monolog\Processor\TraceProcessor` для трасировки записи лога
* Трасировка сконфигурирована для влех Logger
* Доработано поведение логгера PrHtml в административной части
* Косметические правки

## 1.1.2 — 2023-02-16
* Рефакторинг

## 1.1-beta.1 — 2023-02-02
* Initial release
* Добавлена связь с monolog

[↑ Содержание](README.md)