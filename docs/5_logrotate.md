# Ротация логов

Логи модуля лежат в `/local/sh_log/*.log` и сами не чистятся. Ротацию делает
`logrotate` — системная утилита, а не Monolog: Monolog тоже умеет
(`RotatingFileHandler`), но тогда за файлами следит каждый PHP-процесс, а не
одна служба.

Пример настроек — [logrotate/logrotate-b24-shef-problems](logrotate/logrotate-b24-shef-problems).
В модуль он не входит: это файл для сервера, а не для портала. Пути в нём — для
окружения BitrixVM (`/home/bitrix/www`); у вас корень сайта может быть другим.

Два блока с разным сроком хранения:

| файлы | хранится | зачем |
|---|---|---|
| `log`, `log1`, `deprecations`, `log-custom`, `log1-custom` | 2 дня | отладка, нужна «здесь и сейчас» |
| `mailer`, `exceptions`, `sh_problems_*` | 10 дней | проблемы, их разбирают позже |

Ротация — ежедневно или при размере от 5 МБ, со сжатием.

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
