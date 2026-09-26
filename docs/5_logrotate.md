# Ротация логов

> Для ротации файлов в папке `local/sh_log/*.log` используется `logrotate`.
> 
> Через `Monolog` так же можно, но не кошерно.

## Документация
* [Logrotate Ru](https://1cloud.ru/help/linux/upravlenie-logami-s-pomoshch'yu-logrotate-na-ubuntu-16-04)
* [Logrotate Man](https://www.opennet.ru/man.shtml?topic=logrotate&category=8&russian=0)


## Установка
```shell
sudo yum update -y
sudo yum install logrotate -y

sudo logrotate --version
```

## Настройки
Настройки прописываются в `bitrix/modules/shef.problems/logrotate-b24-shef-problems`

> Они будут выполнятся _ежедневно_.
> 
> Если нужно ежечасно, то смотри документацию.

Создаем копию в `/etc/logrotate.d/` и владельцем назначаем `root`:
```shell
sudo cp -i --backup /home/bitrix/www/bitrix/modules/shef.problems/logrotate-b24-shef-problems /etc/logrotate.d/logrotate-b24-shef-problems

sudo chown root:root /etc/logrotate.d/logrotate-b24-shef-problems
```

## Тестирование
```shell
sudo logrotate /etc/logrotate.conf --debug
sudo logrotate -d /etc/logrotate.d/logrotate-b24-shef-problems
```

## Удалить
```shell
sudo rm /etc/logrotate.d/logrotate-b24-shef-problems
```

[← Monolog](docs/4_monolog.md) | [↑ Содержание](README.md)