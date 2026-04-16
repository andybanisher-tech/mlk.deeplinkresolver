<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

IncludeModuleLangFile(__FILE__);

$arModuleVersion = [];
include __DIR__ . '/install/version.php';

$arModuleDescription = [
    'NAME' => GetMessage('MLK_DLR_MODULE_NAME'),
    'DESCRIPTION' => GetMessage('MLK_DLR_MODULE_DESC'),
    'PARTNER_NAME' => GetMessage('MLK_DLR_PARTNER'),
    'PARTNER_URI' => 'https://mlk.company',
    'VERSION' => $arModuleVersion['VERSION'],
    'VERSION_DATE' => $arModuleVersion['VERSION_DATE'],
];
?>