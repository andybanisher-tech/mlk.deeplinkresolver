<?php
/**
 * DeepLink Resolver модуль
 * Получение списка полей и свойств инфоблока
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
header('Content-Type: application/json; charset=utf-8');

$iblockId = (int)($_POST['iblock_id'] ?? $_GET['iblock_id'] ?? 0);
$type = $_POST['type'] ?? $_GET['type'] ?? 'FIELD';
$objectType = $_POST['object_type'] ?? $_GET['object_type'] ?? 'ELEMENT';

if (!Loader::includeModule('iblock') || $iblockId <= 0) {
    echo json_encode(['fields' => []]);
    die();
}

$fields = [];

if ($type === 'FIELD') {
    if ($objectType === 'ELEMENT') {
        $standard = ['ID', 'CODE', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'SORT', 'ACTIVE'];
    } else {
        $standard = ['ID', 'CODE', 'NAME', 'SECTION_PAGE_URL', 'SORT', 'ACTIVE', 'GLOBAL_ACTIVE'];
    }
    foreach ($standard as $code) {
        $fields[] = ['code' => $code, 'name' => $code];
    }
} else {
    $properties = \CIBlockProperty::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']);
    while ($prop = $properties->Fetch()) {
        if (!empty($prop['CODE'])) {
            $fields[] = ['code' => $prop['CODE'], 'name' => '[' . $prop['CODE'] . '] ' . $prop['NAME']];
        }
    }
}
echo json_encode(['fields' => $fields]);
die();