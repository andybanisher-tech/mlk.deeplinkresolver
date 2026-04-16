<?php

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
    // Стандартные поля
    if ($objectType === 'ELEMENT') {
        $standardFields = [
            'ID' => 'ID',
            'CODE' => 'Символьный код',
            'NAME' => 'Название',
            'DETAIL_PAGE_URL' => 'URL детального просмотра',
            'PREVIEW_TEXT' => 'Анонс',
            'DETAIL_TEXT' => 'Детальное описание',
            'SORT' => 'Сортировка',
            'ACTIVE' => 'Активность (Y/N)'
        ];
    } else {
        $standardFields = [
            'ID' => 'ID',
            'CODE' => 'Символьный код',
            'NAME' => 'Название',
            'SECTION_PAGE_URL' => 'URL раздела',
            'SORT' => 'Сортировка',
            'ACTIVE' => 'Активность (Y/N)',
            'GLOBAL_ACTIVE' => 'Активность с учётом родителей'
        ];
    }
    foreach ($standardFields as $code => $name) {
        $fields[] = ['code' => $code, 'name' => $name];
    }
} else {
    // Свойства инфоблока (CIBlockProperty)
    $properties = \CIBlockProperty::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']
    );
    while ($prop = $properties->Fetch()) {
        if (empty($prop['CODE'])) continue;
        $fields[] = [
            'code' => $prop['CODE'],
            'name' => '[' . $prop['CODE'] . '] ' . $prop['NAME']
        ];
    }
}

echo json_encode(['fields' => $fields]);
die();