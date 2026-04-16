<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arModuleVersion = array();
include __DIR__ . '/install/version.php';

$arModuleDescription = array(
    'NAME' => 'МЛК DeepLink Resolver',
    'DESCRIPTION' => 'Модуль для преобразования URL в диплинки на основе правил с поддержкой плейсхолдеров, ручного/автоматического режимов, экспорта/импорта.',
    'PARTNER_NAME' => 'mlk',
    'PARTNER_URI' => 'https://www.mirlk.ru',
    'VERSION' => $arModuleVersion['VERSION'],
    'VERSION_DATE' => $arModuleVersion['VERSION_DATE']
);
?>