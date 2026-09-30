<?php

declare(strict_types=1);

/**
 * .settings.php: ссылки наружу обязаны никуда не висеть.
 *
 * Этот файл — единственное место, где модуль говорит ядру, что и откуда
 * брать: что копировать установщику, откуда грузить Monolog, чьи обработчики
 * событий регистрировать, какие сервисы отдавать. Все ссылаются на файлы,
 * классы и методы ИМЕНЕМ, и ни одну не проверяет ни php -l, ни автозагрузка.
 *
 * Цена промаха одинаково неочевидная: установщик молча ничего не скопирует,
 * автозагрузка молча не найдёт Monolog, обработчик события молча не
 * вызовется. Ни одного сообщения об ошибке установки при этом не будет.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

/** Файл класса модуля по соглашению автозагрузки либо null. */
$classFile = static function (string $class) use ($root): null|string {
    $class = ltrim($class, '\\');
    if (!str_starts_with($class, 'Shef\\Problems\\')) {
        return null;
    }

    $path = $root.'/lib/'.mb_strtolower(str_replace('\\', '/', mb_substr($class, mb_strlen('Shef\\Problems\\')))).'.php';

    return is_file($path) ? $path : null;
};

$hasMethod = static fn (string $file, string $method): bool => 1 === preg_match(
    '/function\s+'.preg_quote($method, '/').'\s*\(/',
    (string)file_get_contents($file)
);

// Корень сайта — каталог над репозиторием: модуль лежит в нём, как в
// bitrix/modules, и путь модуля от корня сайта выходит «/<имя каталога>».
Application::$documentRoot = dirname($root);
$settings = require $root.'/.settings.php';

Check::group('структура файла');

Check::same('.settings.php вернул массив', is_array($settings), true);

$shape = [];
foreach ($settings as $key => $section) {
    if (!is_array($section) || !array_key_exists('value', $section) || !array_key_exists('readonly', $section)) {
        $shape[] = $key;
    }
}

Check::same('у каждой секции есть value и readonly', $shape, []);
Check::same('обязательный модуль — shef.options', $settings['requireModules']['value'], ['shef.options']);

Check::group('registerNamespace — откуда брать Monolog');

$namespaces = $settings['registerNamespace']['value'];

Check::same('свой namespace не регистрируется — его даёт соглашение', isset($namespaces['Shef\\Problems']), false);
Check::same('без shef.options — своя копия Monolog', array_keys($namespaces), ['Monolog']);
Check::same(
    'путь своей копии ведёт в vendor модуля',
    is_file(Application::getDocumentRoot().($namespaces['Monolog'] ?? '').'/Logger.php'),
    true
);

// shef.options есть, но его project-context.php падает: модулю без логгера
// хуже, чем с логгером из своего vendor.
$broken = tempnam(sys_get_temp_dir(), 'ctx');
file_put_contents($broken, '<?php throw new RuntimeException("сломан");');
Loader::$local['modules/shef.options/project-context.php'] = $broken;

$settings = require $root.'/.settings.php';
Check::same(
    'сломанный project-context.php — своя копия',
    $settings['registerNamespace']['value'],
    $namespaces
);

unlink($broken);
Loader::$local = [];

// /local — симлинк: __DIR__ его раскрывает и «выходит» из корня сайта, а
// ядро находит модуль по пути от корня. Путь — тот, что даёт ядро.
Application::$documentRoot = '/home/bitrix/ext_www/site2';
Loader::$local['modules/shef.problems'] = '/home/bitrix/ext_www/site2/local/modules/shef.problems';
$settings = require $root.'/.settings.php';
Check::same(
    'модуль через симлинк /local — путь от корня сайта',
    $settings['registerNamespace']['value']['Monolog'] ?? null,
    '/local/modules/shef.problems/vendor/monolog/monolog/src/Monolog'
);
Loader::$local = [];

// Модуль вне корня сайта (нестандартная раскладка) — путь по умолчанию.
Application::$documentRoot = '/somewhere/else';
$settings = require $root.'/.settings.php';
Check::same(
    'модуль вне корня сайта — путь по умолчанию',
    $settings['registerNamespace']['value']['Monolog'] ?? null,
    '/bitrix/modules/shef.problems/vendor/monolog/monolog/src/Monolog'
);

Check::group('registerNamespace — shef.options решает, Composer или своя копия');

// Настоящий project-context.php из shef.options (копия в tests/stub/): тест
// держит рабочую ветку, а не только запасные. Замена на «всегда своя копия»
// дала бы на проекте с Composer два Monolog.
Application::$documentRoot = dirname($root);
Loader::$local['modules/shef.options/project-context.php'] = $root.'/tests/stub/project-context.php';

$project = sys_get_temp_dir().'/shef-problems-composer-'.getmypid();
register_shutdown_function(static fn () => exec('rm -rf '.escapeshellarg($project)));
mkdir($project.'/vendor', 0777, true);
file_put_contents($project.'/composer.json', '{}');

/** Что решит .settings.php при данной настройке Composer в ядре. */
$decide = static function (?array $composer) use ($root): array {
    // ShComposerContext — синглтон: каждый случай читает настройки заново.
    (new ReflectionProperty(ShComposerContext::class, 'instances'))->setValue(null, []);
    \Bitrix\Main\Config\Configuration::$values = null === $composer ? [] : ['composer' => $composer];

    return (require $root.'/.settings.php')['registerNamespace']['value'];
};

require_once $root.'/tests/stub/project-context.php';
$own = [
    'Monolog' => '/'.basename($root).'/vendor/monolog/monolog/src/Monolog',
];

Check::same('Composer на проекте нет — своя копия', $decide(null), $own);
Check::same('Composer есть, Monolog в нём нет — своя копия', $decide(['config_path' => $project.'/composer.json']), $own);

mkdir($project.'/vendor/monolog/monolog/src/Monolog', 0777, true);
Check::same('Monolog в vendor проекта — своя копия не регистрируется', $decide(['config_path' => $project.'/composer.json']), []);

\Bitrix\Main\Config\Configuration::$values = [];
(new ReflectionProperty(ShComposerContext::class, 'instances'))->setValue(null, []);
Loader::$local = [];

Application::$documentRoot = dirname($root);
$settings = require $root.'/.settings.php';

Check::group('installDir — что копирует установщик');

$installDir = $settings['installDir']['value'] ?? [];

Check::same('installDir не пуст', !empty($installDir), true);

$missing = [];
$wrongTarget = [];

foreach ($installDir as $i => $map) {
    $from = (string)($map['from'] ?? '');
    $to = (string)($map['to'] ?? '');

    if ($from === '' || !is_dir($root.$from)) {
        $missing[] = sprintf('запись %d: каталога %s в репозитории нет', $i, $from);
    }

    // Каталог модуля браузеру недоступен, поэтому «куда» — всегда под /bitrix/.
    if (!str_starts_with($to, '/bitrix/')) {
        $wrongTarget[] = sprintf('запись %d: to = %s', $i, $to);
    }
}

Check::same('каждый from существует', $missing, []);
Check::same('каждый to ведёт под /bitrix/', $wrongTarget, []);

Check::group('installEvents — обработчики существуют');

$brokenHandlers = [];
foreach ($settings['installEvents']['value'] as $i => $event) {
    $class = (string)($event['to']['class'] ?? '');
    $method = (string)($event['to']['function'] ?? '');
    $file = $classFile($class);

    if (($event['to']['module'] ?? '') !== 'shef.problems') {
        $brokenHandlers[] = sprintf('запись %d: обработчик не в этом модуле', $i);
    } elseif (null === $file) {
        $brokenHandlers[] = sprintf('запись %d: класса %s нет', $i, $class);
    } elseif (!$hasMethod($file, $method)) {
        $brokenHandlers[] = sprintf('запись %d: метода %s::%s нет', $i, $class, $method);
    }
}

Check::same('каждый обработчик — существующий метод', $brokenHandlers, []);

$fromModules = array_unique(array_map(static fn (array $event): string => $event['from']['module'], $settings['installEvents']['value']));
Check::same('события только ядра — от shef.uiclear модуль не зависит', array_values($fromModules), ['main']);

Check::group('services — каждый логгер enum на месте');

require_once $root.'/tests/stub/autoload.php';

$services = $settings['services']['value'];
$missingServices = [];
foreach (\Shef\Problems\Logger::cases() as $case) {
    if (!isset($services[$case->getServiceName()])) {
        $missingServices[] = $case->name;
    }
}

Check::same('у каждого случая Logger есть сервис', $missingServices, []);

$badHandlerType = [];
foreach ($services as $name => $service) {
    foreach ((array)($service['handlerType'] ?? []) as $type) {
        if (!in_array($type, \Shef\Problems\Main\Constants::getHandlerTypeList(), true)) {
            $badHandlerType[] = $name.': '.$type;
        }
    }

    if (isset($service['className']) && !class_exists($service['className'])) {
        $badHandlerType[] = $name.': класса '.$service['className'].' нет';
    }
}

Check::same('handlerType из известных, className существует', $badHandlerType, []);

Check::group('controllers');

Check::same('своих ajax-контроллеров нет', $settings['controllers']['value']['namespaces'], []);

Check::finish();
