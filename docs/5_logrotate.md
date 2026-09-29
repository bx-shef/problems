# Ротация логов

Логи модуля лежат в каталоге логов — по умолчанию на уровень выше корня сайта,
для BitrixVM `/home/bitrix/sh_log/*.log`, — и сами не чистятся. Ротацию делает
`logrotate` — системная утилита, а не Monolog: Monolog тоже умеет
(`RotatingFileHandler`), но тогда за файлами следит каждый PHP-процесс, а не
одна служба.

Пример настроек — [logrotate/logrotate-b24-shef-problems](logrotate/logrotate-b24-shef-problems).
В модуль он не входит: это файл для сервера, а не для портала. Пути в нём — для
окружения BitrixVM (`/home/bitrix/sh_log`); если корень сайта другой или каталог
задан в настройках проекта — поправьте. До 2.0.0 пути вели в
`/home/bitrix/www/local/sh_log` — обновляясь с 1.x, замените их.

Два блока с разным сроком хранения:

| файлы | хранится | зачем |
|---|---|---|
| `log`, `log1`, `deprecations`, `log-custom`, `log1-custom` | 2 дня | отладка, нужна «здесь и сейчас» |
| `mailer`, `exceptions`, `sh_problems_*` | 10 дней | проблемы, их разбирают позже |

Ротация — раз в сутки, со сжатием. `maxsize 5M` проверяется, только когда
logrotate запущен: из штатного cron — тоже раз в сутки; запускайте чаще, чтобы
резать и по размеру.

**Без logrotate диск тоже не забьётся:** отладочный лог и файлы проблем пишет
`CappedStreamHandler` — перерос 20 МБ, файл откладывается в `<имя>.1` и
начинается новый. logrotate нужен для сжатия и срока хранения, а не как
единственная защита.

**`dateext` обязателен.** Без него logrotate называет ротированный файл тем же
`<имя>.1`, что и `CappedStreamHandler`, а с `delaycompress` оставляет его
несжатым — и следующее переполнение затирает ротацию `rename()`. С
`dateext` у logrotate свои имена (`log.log-20260929-1790706841`), `<имя>.1`
остаётся за модулем. Проверено logrotate 3.21 на песочнице. Файлы с датой
страница логов модуля не показывает — только `<имя>.log` и `<имя>.log.N`.

**`copytruncate` не нужен.** Его добавляли, пока Monolog после ротации
продолжал писать в переименованный файл. С Monolog 3.10 `StreamHandler` сам
переоткрывает файл, когда у пути сменился inode, а `copytruncate` теряет
строки, записанные между копированием и обрезкой.

## Документация

* [Logrotate, по-русски](https://1cloud.ru/help/linux/upravlenie-logami-s-pomoshch'yu-logrotate-na-ubuntu-16-04)
* [man logrotate](https://www.opennet.ru/man.shtml?topic=logrotate&category=8&russian=0)

## Установка

```shell
sudo yum install logrotate -y      # или apt install logrotate
sudo logrotate --version
```

## Настройки

Скопировать пример в `/etc/logrotate.d/`, поправить пути под свой корень сайта,
владелец — `root`:

```shell
sudo cp -i logrotate-b24-shef-problems /etc/logrotate.d/logrotate-b24-shef-problems
sudo chown root:root /etc/logrotate.d/logrotate-b24-shef-problems
```

Файл берётся из репозитория, из `docs/logrotate/`: с 2.0.0 он не лежит в
каталоге модуля на портале.

## Проверка

```shell
sudo logrotate -d /etc/logrotate.d/logrotate-b24-shef-problems
```

`-d` — сухой прогон: печатает, что сделал бы, и ничего не трогает.

## Удалить

```shell
sudo rm /etc/logrotate.d/logrotate-b24-shef-problems
```

[← Monolog](4_monolog.md) | [↑ Содержание](../README.md) | [Безопасность логов →](security.md)
