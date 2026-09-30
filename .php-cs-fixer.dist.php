<?php

declare(strict_types=1);

/**
 * Правила форматирования PHP — как в shef.options.
 *
 * Гоняются отдельной целью composer и отдельным шагом CI, а НЕ из build.sh.
 * Сборке хватает php, git и zip, и она обязана отрабатывать в свежем клоне;
 * позови она линтер — и ./build.sh --check перестал бы запускаться без
 * composer install. Поэтому проверок две, и обе обязательные.
 *
 * Набор правил — @PSR12 целиком, решение владельца. Версия инструмента в
 * composer.json пришпилена ТОЧНО, без «^»: набор @PSR12 у php-cs-fixer
 * пополняется в минорных выпусках, и с «^3.0» CI однажды покраснел бы на
 * коммите, который ничего не менял.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    // Точечные файлы Symfony Finder пропускает по умолчанию, а .settings.php
    // и сам этот файл — такие же исходники, и правила обязаны соблюдать.
    ->ignoreDotFiles(false)
    // Вместе с точечными открылись бы и каталоги из .gitignore: /.versions,
    // /bitrix-version-builder с копиями модуля и vendor-dev/ с инструментами.
    // Своя копия Monolog в vendor/ — чужой код как есть: этот каталог базовый
    // Finder исключает сам.
    ->ignoreVCSIgnored(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder);
