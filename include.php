<?php

// def-functions.php — ПЕРВЫМ, до autoload.php, и порядок здесь не случаен.
//
// _log(), _log1() и _pr() объявляет и shef.options, а autoload.php подключает
// его как обязательный модуль. Каждая функция закрыта function_exists:
// побеждает тот, кто объявил первым. Пойди autoload.php первым — функции
// всегда брались бы из shef.options. Его _log() пишет вне корня сайта, в
// shef_options_log_dir(): константа SHEF_OPTIONS_LOG_DIR, иначе каталог логов
// этого модуля (Constants::getLogDir()) и далее по списку — обычно в то же место,
// но каталог выбирал бы shef.options, а не этот модуль.
//
// Первенство не абсолютное: если shef.options подключили раньше этого модуля
// в том же запросе, его функции уже объявлены. Выше обоих — версия проекта из
// php_interface/def-functions.php. @see docs/2_deffunctions.md, «Чьи функции победят».
require_once __DIR__.'/def-functions.php';

require_once __DIR__.'/autoload.php';
