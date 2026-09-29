<?php

// def-functions.php — ПЕРВЫМ, до autoload.php, и порядок здесь не случаен.
//
// _log(), _log1() и _pr() объявляет и shef.options, а autoload.php подключает
// его как обязательный модуль. Каждая функция закрыта function_exists:
// побеждает тот, кто объявил первым. Пойди autoload.php первым — функции
// всегда брались бы из shef.options и писали бы в его /local/log, а не в
// каталог логов этого модуля.
//
// Первенство не абсолютное: если shef.options подключили раньше этого модуля
// в том же запросе, его функции уже объявлены. Выше обоих — версия проекта из
// php_interface/def-functions.php. @see docs/2_deffunctions.md, «Чьи функции победят».
require_once __DIR__.'/def-functions.php';

require_once __DIR__.'/autoload.php';
