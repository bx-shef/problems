<?php

declare(strict_types=1);

/**
 * Табов вне строковых литералов нет — там, куда линтер не дотягивается.
 *
 * php-cs-fixer (composer run lint) правит PHP-токены, а инлайновый HTML между
 * ?> и <?php для него текст: таб в разметке admin/logs.php он пропускает, и CI
 * остаётся зелёным. После перехода на @PSR12 такие места доводились отдельно
 * (bx-shef/options#38, здесь #17) — этот тест держит результат.
 *
 * Мерка — по токенам, а не grep '^\t': grep не видит таб после пробелов и не
 * отличает код от литерала. Табы внутри строковых литералов и heredoc/nowdoc —
 * данные программы, они не считаются (nowdoc в include_test.php — такой).
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

/** Сколько табов в файле вне строковых литералов. */
$tabsOutsideStrings = static function (string $source): int {
    $count = 0;
    $quoted = false;
    $heredoc = false;
    foreach (token_get_all($source) as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;
        if (T_START_HEREDOC === $id) {
            $heredoc = true;
        } elseif (T_END_HEREDOC === $id) {
            $heredoc = false;
        }
        if ('"' === $text || '`' === $text) {
            $quoted = !$quoted;
        }
        $isString = $quoted || $heredoc || in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true);
        if (!$isString) {
            $count += substr_count($text, "\t");
        }
    }

    return $count;
};

Check::group('мерка сама не слепа');

Check::same('таб в инлайновом HTML виден', $tabsOutsideStrings("<?php echo 1; ?>\n\t<div>\n"), 1);
Check::same('таб в отступе кода виден', $tabsOutsideStrings("<?php\nif (true) {\n\techo 1;\n}\n"), 1);
Check::same('таб в строке — данные', $tabsOutsideStrings("<?php\n\$a = \"x\ty\";\n\$b = 'x\ty';\n"), 0);
Check::same('таб в nowdoc — данные', $tabsOutsideStrings("<?php\n\$a = <<<'T'\n\tx\nT;\n"), 0);

Check::group('табов вне литералов нет');

// Файлы под git: игнорируемые каталоги (старые копии модуля) не в счёт.
// vendor/ — своя копия Monolog, чужой код как есть.
exec('git -C '.escapeshellarg($root).' ls-files -- '.escapeshellarg('*.php'), $tracked, $code);
Check::same('git ls-files отработал', $code, 0);

$files = array_values(array_filter($tracked, static fn (string $path): bool => !str_starts_with($path, 'vendor/')));
Check::same('файлов найдено — проверка не впустую', count($files) > 80, true);

$withTabs = [];
foreach ($files as $path) {
    $tabs = $tabsOutsideStrings((string)file_get_contents($root.'/'.$path));
    if ($tabs > 0) {
        $withTabs[] = $path.': '.$tabs;
    }
}
Check::same('ни одного таба вне строковых литералов', $withTabs, []);

Check::finish();
