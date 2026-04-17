<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Mlk\DlResolver\Resolver\RuleTable;

$module_id = 'mlk.dlresolver';
Loader::includeModule($module_id);
Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/options.php');
Loc::loadMessages(__FILE__);

$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$action = $request->get('action');
$editId = (int)$request->get('edit');
$sTableID = 'tbl_rule_list';

// ---------- ЭКСПОРТ (GET) ----------
if ($action === 'export' && check_bitrix_sessid()) {
    $rules = RuleTable::getList(['select' => ['*']])->fetchAll();
    $exportData = [];
    foreach ($rules as $rule) {
        unset($rule['ID'], $rule['CREATED_AT'], $rule['UPDATED_AT']);
        $exportData[] = $rule;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="deeplink_rules_export.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die();
}

// ---------- ИМПОРТ (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request->getPost('import') === 'Y' && check_bitrix_sessid()) {
    $file = $_FILES['import_file'];
    if ($file && $file['error'] === UPLOAD_ERR_OK && $file['type'] === 'application/json') {
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        if (is_array($data)) {
            $imported = 0;
            foreach ($data as $ruleData) {
                if (empty($ruleData['URL_TEMPLATE']) || empty($ruleData['NAME']) || empty($ruleData['IBLOCK_ID'])) {
                    continue;
                }
                $existing = RuleTable::getList(['filter' => ['=NAME' => $ruleData['NAME']], 'limit' => 1])->fetch();
                $fields = [
                    'ACTIVE' => $ruleData['ACTIVE'] ?? 'Y',
                    'SORT' => (int)($ruleData['SORT'] ?? 500),
                    'NAME' => $ruleData['NAME'],
                    'URL_TEMPLATE' => $ruleData['URL_TEMPLATE'],
                    'OBJECT_TYPE' => $ruleData['OBJECT_TYPE'] ?? 'ELEMENT',
                    'IBLOCK_ID' => (int)$ruleData['IBLOCK_ID'],
                    'CONTENT_TYPE' => $ruleData['CONTENT_TYPE'],
                    'DEEPLINK_MODE' => $ruleData['DEEPLINK_MODE'] ?? 'auto',
                    'DEEPLINK_SOURCE' => $ruleData['DEEPLINK_SOURCE'] ?? 'FIELD',
                    'DEEPLINK_CODE' => $ruleData['DEEPLINK_CODE'] ?? '',
                    'DEEPLINK_TEMPLATE' => $ruleData['DEEPLINK_TEMPLATE'] ?? null,
                    'PLACEHOLDER_MAPPING' => $ruleData['PLACEHOLDER_MAPPING'] ?? '{}',
                    'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
                ];
                if ($existing) {
                    RuleTable::update($existing['ID'], $fields);
                } else {
                    $fields['CREATED_AT'] = new \Bitrix\Main\Type\DateTime();
                    RuleTable::add($fields);
                }
                $imported++;
            }
            CAdminMessage::ShowMessage(['MESSAGE' => "Импортировано правил: $imported", 'TYPE' => 'OK']);
        } else {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Неверный формат JSON', 'TYPE' => 'ERROR']);
        }
    } else {
        CAdminMessage::ShowMessage(['MESSAGE' => 'Ошибка загрузки файла', 'TYPE' => 'ERROR']);
    }
}

// ---------- УДАЛЕНИЕ ЧЕРЕЗ POST (НОВЫЙ МЕТОД) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request->getPost('delete_rule') === 'Y' && $editId > 0 && check_bitrix_sessid()) {
    RuleTable::delete($editId);
    LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . $module_id . '&lang=' . LANGUAGE_ID);
}

// ---------- ОБРАБОТКА POST (СОХРАНЕНИЕ ПРАВИЛА) ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid() && $request->getPost('save') !== null) {
    $editId = (int)$request->getPost('ID');
    $isNew = ($editId <= 0);

    $fields = [
        'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
        'SORT' => (int)$request->getPost('SORT'),
        'NAME' => trim($request->getPost('NAME')),
        'URL_TEMPLATE' => trim($request->getPost('URL_TEMPLATE')),
        'OBJECT_TYPE' => $request->getPost('OBJECT_TYPE'),
        'IBLOCK_ID' => (int)$request->getPost('IBLOCK_ID'),
        'CONTENT_TYPE' => trim($request->getPost('CONTENT_TYPE')),
        'DEEPLINK_MODE' => $request->getPost('DEEPLINK_MODE') === 'manual' ? 'manual' : 'auto',
        'DEEPLINK_SOURCE' => $request->getPost('DEEPLINK_SOURCE'),
        'DEEPLINK_CODE' => trim($request->getPost('DEEPLINK_CODE')),
        'DEEPLINK_TEMPLATE' => $request->getPost('DEEPLINK_MODE') === 'manual' ? trim($request->getPost('DEEPLINK_TEMPLATE')) : null,
        'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
    ];

    // Маппинг плейсхолдеров
    $placeholders = $request->getPost('placeholder');
    $sourceTypes = $request->getPost('placeholder_source_type');
    $sourceCodes = $request->getPost('placeholder_source_code');
    $mapping = [];
    if (is_array($placeholders)) {
        foreach ($placeholders as $idx => $ph) {
            $ph = trim($ph);
            $ph = trim($ph, '{}');
            if ($ph === '') continue;
            $type = isset($sourceTypes[$idx]) ? $sourceTypes[$idx] : 'FIELD';
            $code = isset($sourceCodes[$idx]) ? trim($sourceCodes[$idx]) : '';
            if ($code === '') continue;
            $mapping[$ph] = ($type === 'PROPERTY' ? 'PROPERTY:' : 'FIELD:') . $code;
        }
    }
    $fields['PLACEHOLDER_MAPPING'] = json_encode($mapping, JSON_UNESCAPED_UNICODE);

    if ($isNew) {
        $fields['CREATED_AT'] = new \Bitrix\Main\Type\DateTime();
        RuleTable::add($fields);
    } else {
        RuleTable::update($editId, $fields);
    }
    LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . $module_id . '&lang=' . LANGUAGE_ID);
}

$isEdit = ($editId > 0 && $request->get('action') === 'edit') || $request->get('action') === 'new';

// Загрузка данных для редактирования
$ruleData = null;
$mapping = [];
if ($editId > 0 && $request->get('action') === 'edit') {
    $ruleData = RuleTable::getById($editId)->fetch();
    if ($ruleData) {
        $mappingRaw = json_decode($ruleData['PLACEHOLDER_MAPPING'], true);
        if (is_array($mappingRaw)) $mapping = $mappingRaw;
    }
}
if ($request->get('action') === 'new') {
    $ruleData = [
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'NAME' => '',
        'URL_TEMPLATE' => '',
        'OBJECT_TYPE' => 'ELEMENT',
        'IBLOCK_ID' => 0,
        'CONTENT_TYPE' => '',
        'DEEPLINK_MODE' => 'auto',
        'DEEPLINK_SOURCE' => 'FIELD',
        'DEEPLINK_CODE' => '',
        'DEEPLINK_TEMPLATE' => '',
    ];
    $mapping = [];
    $editId = 0;
}

$tabs = [
    ['DIV' => 'rules', 'TAB' => Loc::getMessage('MLK_DL_RULES_TAB'), 'TITLE' => Loc::getMessage('MLK_DL_RULES_TAB_TITLE')],
];
$tabControl = new CAdminTabControl('tabControl', $tabs);

?>
<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&lang=<?=LANGUAGE_ID?>">
<?=bitrix_sessid_post()?>
<? $tabControl->Begin(); ?>
<? $tabControl->BeginNextTab(); ?>

<? if (!$isEdit): ?>
    <!-- СПИСОК ПРАВИЛ -->
    <div style="margin-bottom: 15px;">
        <input type="button" value="<?=Loc::getMessage('MLK_DL_ADD_RULE')?>" onclick="window.location='<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&lang=<?=LANGUAGE_ID?>&action=new'">
        <span style="margin-left: 20px;"></span>
        <input type="button" value="Экспорт правил" onclick="window.location='<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&lang=<?=LANGUAGE_ID?>&action=export&<?=bitrix_sessid_get()?>'">
        <form method="post" enctype="multipart/form-data" style="display: inline-block; margin-left: 10px;">
            <?=bitrix_sessid_post()?>
            <input type="hidden" name="import" value="Y">
            <input type="file" name="import_file" accept="application/json" style="display: inline-block; width: auto;">
            <input type="submit" value="Импортировать правила">
        </form>
    </div>
    <?
    $rules = RuleTable::getList(['order' => ['SORT' => 'ASC', 'ID' => 'ASC']])->fetchAll();
    ?>
    <table class="adm-list-table" style="width:100%">
        <thead>
        <tr class="adm-list-table-header">
            <td class="adm-list-table-cell">ID</td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_ACTIVE')?></td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_SORT')?></td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_NAME')?></td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_CONTENT_TYPE')?></td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_IBLOCK')?></td>
            <td class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_RULE_ACTIONS')?></td>
        </tr>
        </thead>
        <tbody>
        <? foreach ($rules as $rule):
            $iblockName = '';
            if ($rule['IBLOCK_ID'] > 0 && Loader::includeModule('iblock')) {
                $iblock = CIBlock::GetByID($rule['IBLOCK_ID'])->Fetch();
                $iblockName = $iblock ? '[' . $rule['IBLOCK_ID'] . '] ' . htmlspecialcharsbx($iblock['NAME']) : $rule['IBLOCK_ID'];
            }
        ?>
            <tr class="adm-list-table-row">
                <td class="adm-list-table-cell"><?=$rule['ID']?></td>
                <td class="adm-list-table-cell"><?=($rule['ACTIVE'] == 'Y' ? Loc::getMessage('MLK_DL_YES') : Loc::getMessage('MLK_DL_NO'))?></td>
                <td class="adm-list-table-cell"><?=$rule['SORT']?></td>
                <td class="adm-list-table-cell"><?=htmlspecialcharsbx($rule['NAME'])?></td>
                <td class="adm-list-table-cell"><?=htmlspecialcharsbx($rule['CONTENT_TYPE'])?></td>
                <td class="adm-list-table-cell"><?=htmlspecialcharsbx($iblockName)?></td>
                <td class="adm-list-table-cell">
                    <a href="<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&lang=<?=LANGUAGE_ID?>&edit=<?=$rule['ID']?>&action=edit"><?=Loc::getMessage('MLK_DL_EDIT')?></a>
                    &nbsp;|&nbsp;
                    <!-- POST-форма для удаления -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('<?=Loc::getMessage('MLK_DL_CONFIRM_DELETE')?>')">
                        <?=bitrix_sessid_post()?>
                        <input type="hidden" name="delete_rule" value="Y">
                        <input type="hidden" name="edit" value="<?=$rule['ID']?>">
                        <input type="submit" value="<?=Loc::getMessage('MLK_DL_DELETE')?>" style="background:none; border:none; color:red; cursor:pointer; padding:0; margin:0;">
                    </form>
                </td>
            </tr>
        <? endforeach; ?>
        <? if (empty($rules)): ?>
            <tr><td colspan="7" class="adm-list-table-cell"><?=Loc::getMessage('MLK_DL_NO_RULES')?></td></tr>
        <? endif; ?>
        </tbody>
    </table>
<? else: ?>
    <!-- ФОРМА РЕДАКТИРОВАНИЯ -->
    <input type="hidden" name="ID" value="<?=$editId?>">
    <input type="hidden" name="save" value="Y">
    <table class="adm-detail-content-table edit-table">
        <!-- Активность -->
        <tr>
            <td width="40%"><?=Loc::getMessage('MLK_DL_RULE_ACTIVE')?>:</td>
            <td width="60%"><input type="checkbox" name="ACTIVE" value="Y" <?=($ruleData['ACTIVE']=='Y' ? 'checked' : '')?>></td>
        </tr>
        <!-- Сортировка -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_SORT')?>:</td>
            <td><input type="text" name="SORT" value="<?=$ruleData['SORT']?>" size="5"></td>
        </tr>
        <!-- Название -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_NAME')?> <span class="required">*</span>:</td>
            <td><input type="text" name="NAME" value="<?=htmlspecialcharsbx($ruleData['NAME'])?>" style="width:100%"></td>
        </tr>
        <!-- Шаблон URL -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_URL_TEMPLATE')?> <span class="required">*</span>:<br><small><?=Loc::getMessage('MLK_DL_URL_TEMPLATE_HINT')?></small></td>
            <td><input type="text" name="URL_TEMPLATE" value="<?=htmlspecialcharsbx($ruleData['URL_TEMPLATE'])?>" style="width:100%"></td>
        </tr>
        <!-- Маппинг плейсхолдеров -->
        <tr>
            <td valign="top"><?=Loc::getMessage('MLK_DL_PLACEHOLDER_MAPPING')?>:</td>
            <td>
                <div id="mapping-container">
                    <? foreach ($mapping as $placeholder => $fieldCode):
                        $type = (strpos($fieldCode, 'PROPERTY:') === 0) ? 'PROPERTY' : 'FIELD';
                        $code = ($type === 'PROPERTY') ? substr($fieldCode, 9) : substr($fieldCode, 6);
                    ?>
                        <div class="mapping-row" style="margin-bottom:5px;">
                            <input type="text" name="placeholder[]" value="<?=htmlspecialcharsbx($placeholder)?>" placeholder="плейсхолдер" style="width:120px;">
                            <select name="placeholder_source_type[]">
                                <option value="FIELD" <?=($type=='FIELD' ? 'selected' : '')?>><?=Loc::getMessage('MLK_DL_SOURCE_FIELD')?></option>
                                <option value="PROPERTY" <?=($type=='PROPERTY' ? 'selected' : '')?>><?=Loc::getMessage('MLK_DL_SOURCE_PROPERTY')?></option>
                            </select>
                            <select name="placeholder_source_code[]" class="field-select" data-current="<?=htmlspecialcharsbx($code)?>">
                                <option value="">-- выберите --</option>
                            </select>
                            <button type="button" onclick="this.parentNode.remove()"><?=Loc::getMessage('MLK_DL_DELETE')?></button>
                        </div>
                    <? endforeach; ?>
                </div>
                <button type="button" onclick="addMappingRow()"><?=Loc::getMessage('MLK_DL_ADD_PLACEHOLDER')?></button>
            </td>
        </tr>
        <!-- Тип объекта -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_OBJECT_TYPE')?>:</td>
            <td>
                <select name="OBJECT_TYPE" id="object-type-select">
                    <option value="ELEMENT" <?=($ruleData['OBJECT_TYPE']=='ELEMENT' ? 'selected' : '')?>><?=Loc::getMessage('MLK_DL_OBJECT_ELEMENT')?></option>
                    <option value="SECTION" <?=($ruleData['OBJECT_TYPE']=='SECTION' ? 'selected' : '')?>><?=Loc::getMessage('MLK_DL_OBJECT_SECTION')?></option>
                </select>
            </td>
        </tr>
        <!-- Инфоблок -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_IBLOCK')?> <span class="required">*</span>:</td>
            <td>
                <select name="IBLOCK_ID" id="iblock-select">
                    <option value="0"><?=Loc::getMessage('MLK_DL_SELECT_IBLOCK')?></option>
                    <? if (Loader::includeModule('iblock')): ?>
                        <? $dbIBlock = CIBlock::GetList(['SORT'=>'ASC','NAME'=>'ASC'], ['ACTIVE'=>'Y']); ?>
                        <? while ($ib = $dbIBlock->Fetch()): ?>
                            <option value="<?=$ib['ID']?>" <?=($ruleData['IBLOCK_ID']==$ib['ID'] ? 'selected' : '')?>><?='['.$ib['ID'].'] '.htmlspecialcharsbx($ib['NAME'])?></option>
                        <? endwhile; ?>
                    <? endif; ?>
                </select>
            </td>
        </tr>
        <!-- Тип контента -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_RULE_CONTENT_TYPE')?> <span class="required">*</span>:</td>
            <td><input type="text" name="CONTENT_TYPE" value="<?=htmlspecialcharsbx($ruleData['CONTENT_TYPE'])?>" style="width:100%"></td>
        </tr>
        <!-- Режим формирования диплинка -->
        <tr>
            <td><?=Loc::getMessage('MLK_DL_DEEPLINK_MODE')?>:</td>
            <td>
                <label><input type="radio" name="DEEPLINK_MODE" value="auto" <?=($ruleData['DEEPLINK_MODE']=='auto'?'checked':'')?> onchange="toggleDeeplinkMode()"> <?=Loc::getMessage('MLK_DL_MODE_AUTO')?></label><br>
                <label><input type="radio" name="DEEPLINK_MODE" value="manual" <?=($ruleData['DEEPLINK_MODE']=='manual'?'checked':'')?> onchange="toggleDeeplinkMode()"> <?=Loc::getMessage('MLK_DL_MODE_MANUAL')?></label>
            </td>
        </tr>
        <!-- Блок выбора поля/свойства (общий) -->
        <tbody id="source-block">
        <tr>
            <td id="source-label"><?=Loc::getMessage('MLK_DL_DEEPLINK_SOURCE_AUTO')?>:</td>
            <td>
                <select name="DEEPLINK_SOURCE" id="deeplink-source">
                    <option value="FIELD" <?=($ruleData['DEEPLINK_SOURCE']=='FIELD'?'selected':'')?>><?=Loc::getMessage('MLK_DL_SOURCE_FIELD')?></option>
                    <option value="PROPERTY" <?=($ruleData['DEEPLINK_SOURCE']=='PROPERTY'?'selected':'')?>><?=Loc::getMessage('MLK_DL_SOURCE_PROPERTY')?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td id="code-label"><?=Loc::getMessage('MLK_DL_DEEPLINK_CODE_AUTO')?> <span class="required">*</span>:</td>
            <td>
                <select name="DEEPLINK_CODE" id="deeplink-code-select">
                    <option value="">-- выберите --</option>
                </select>
            </td>
        </tr>
        <tr id="source-hint-row">
            <td colspan="2" style="color:#666; font-size:11px;" id="source-hint">
                <?=Loc::getMessage('MLK_DL_SOURCE_HINT_AUTO')?>
            </td>
        </tr>
        </tbody>
        <!-- Блок шаблона диплинка (ручной режим) -->
        <tbody id="manual-template-block" style="display:none;">
        <tr>
            <td valign="top"><?=Loc::getMessage('MLK_DL_DEEPLINK_TEMPLATE')?> <span class="required">*</span>:<br><small><?=Loc::getMessage('MLK_DL_DEEPLINK_TEMPLATE_HINT')?></small></td>
            <td>
                <textarea name="DEEPLINK_TEMPLATE" rows="3" style="width:100%"><?=htmlspecialcharsbx($ruleData['DEEPLINK_TEMPLATE'] ?? '')?></textarea>
            </td>
        </tr>
        </tbody>
    </table>
    <br>
    <input type="submit" value="<?=Loc::getMessage('MLK_DL_SAVE')?>">
    <input type="button" value="<?=Loc::getMessage('MLK_DL_CANCEL')?>" onclick="window.location='<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($module_id)?>&lang=<?=LANGUAGE_ID?>'">
<? endif; ?>

<? $tabControl->End(); ?>
</form>

<script>
function addMappingRow() {
    var container = document.getElementById('mapping-container');
    var row = document.createElement('div');
    row.className = 'mapping-row';
    row.style.marginBottom = '5px';
    row.innerHTML = `
        <input type="text" name="placeholder[]" placeholder="плейсхолдер" style="width:120px;">
        <select name="placeholder_source_type[]">
            <option value="FIELD"><?=Loc::getMessage('MLK_DL_SOURCE_FIELD')?></option>
            <option value="PROPERTY"><?=Loc::getMessage('MLK_DL_SOURCE_PROPERTY')?></option>
        </select>
        <select name="placeholder_source_code[]" class="field-select">
            <option value="">-- выберите --</option>
        </select>
        <button type="button" onclick="this.parentNode.remove()"><?=Loc::getMessage('MLK_DL_DELETE')?></button>
    `;
    container.appendChild(row);
    updateFieldSelects();
}

function updateFieldSelects() {
    var iblockId = document.getElementById('iblock-select').value;
    var sourceType = document.getElementById('deeplink-source').value;
    var objectType = document.getElementById('object-type-select').value;
    loadFieldsForSelect('deeplink-code-select', iblockId, sourceType, objectType, '<?=htmlspecialcharsbx($ruleData['DEEPLINK_CODE'] ?? '')?>');
    var mappingSelects = document.querySelectorAll('.field-select');
    mappingSelects.forEach(function(select) {
        var row = select.closest('.mapping-row');
        var typeSelect = row.querySelector('select[name="placeholder_source_type[]"]');
        var type = typeSelect.value;
        var currentValue = select.getAttribute('data-current') || select.value;
        loadFieldsForSelect(select, iblockId, type, objectType, currentValue);
    });
}

function loadFieldsForSelect(selectElement, iblockId, sourceType, objectType, selectedValue) {
    if (!iblockId || iblockId == 0) {
        var options = '<option value="">-- сначала выберите инфоблок --</option>';
        if (typeof selectElement === 'string') {
            var sel = document.getElementById(selectElement);
            if (sel) sel.innerHTML = options;
        } else {
            selectElement.innerHTML = options;
        }
        return;
    }
    var url = '/bitrix/tools/mlk.dlresolver/get_fields.php';
    var body = 'iblock_id=' + iblockId + '&type=' + sourceType + '&object_type=' + objectType;
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    })
    .then(response => response.json())
    .then(data => {
        var options = '<option value="">-- выберите --</option>';
        if (data.fields) {
            data.fields.forEach(function(field) {
                var selected = (field.code == selectedValue) ? 'selected' : '';
                options += '<option value="' + field.code + '" ' + selected + '>' + field.name + '</option>';
            });
        }
        if (typeof selectElement === 'string') {
            var sel = document.getElementById(selectElement);
            if (sel) sel.innerHTML = options;
        } else {
            selectElement.innerHTML = options;
            if (selectedValue && !selectElement.value) {
                selectElement.value = selectedValue;
            }
        }
    })
    .catch(err => console.error(err));
}

function toggleDeeplinkMode() {
    var mode = document.querySelector('input[name="DEEPLINK_MODE"]:checked').value;
    var templateBlock = document.getElementById('manual-template-block');
    var sourceLabel = document.getElementById('source-label');
    var codeLabel = document.getElementById('code-label');
    var sourceHint = document.getElementById('source-hint');
    if (mode === 'auto') {
        templateBlock.style.display = 'none';
        sourceLabel.innerHTML = '<?=Loc::getMessage('MLK_DL_DEEPLINK_SOURCE_AUTO')?>:';
        codeLabel.innerHTML = '<?=Loc::getMessage('MLK_DL_DEEPLINK_CODE_AUTO')?> <span class="required">*</span>:';
        sourceHint.innerHTML = '<?=Loc::getMessage('MLK_DL_SOURCE_HINT_AUTO')?>';
    } else {
        templateBlock.style.display = '';
        sourceLabel.innerHTML = '<?=Loc::getMessage('MLK_DL_DEEPLINK_SOURCE_MANUAL')?>:';
        codeLabel.innerHTML = '<?=Loc::getMessage('MLK_DL_DEEPLINK_CODE_MANUAL')?> <span class="required">*</span>:';
        sourceHint.innerHTML = '<?=Loc::getMessage('MLK_DL_SOURCE_HINT_MANUAL')?>';
    }
}

document.getElementById('iblock-select').addEventListener('change', function() {
    updateFieldSelects();
});
document.getElementById('deeplink-source').addEventListener('change', function() {
    updateFieldSelects();
});
document.getElementById('object-type-select').addEventListener('change', function() {
    updateFieldSelects();
});
document.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'placeholder_source_type[]') {
        var row = e.target.closest('.mapping-row');
        var select = row.querySelector('.field-select');
        var iblockId = document.getElementById('iblock-select').value;
        var objectType = document.getElementById('object-type-select').value;
        var type = e.target.value;
        var currentValue = select.getAttribute('data-current') || '';
        loadFieldsForSelect(select, iblockId, type, objectType, currentValue);
    }
});
document.addEventListener('DOMContentLoaded', function() {
    toggleDeeplinkMode();
    updateFieldSelects();
    var existingRows = document.querySelectorAll('.mapping-row');
    existingRows.forEach(function(row) {
        var typeSelect = row.querySelector('select[name="placeholder_source_type[]"]');
        var codeSelect = row.querySelector('.field-select');
        var iblockId = document.getElementById('iblock-select').value;
        var objectType = document.getElementById('object-type-select').value;
        var type = typeSelect.value;
        var currentCode = codeSelect.getAttribute('data-current') || codeSelect.value;
        loadFieldsForSelect(codeSelect, iblockId, type, objectType, currentCode);
    });
});
</script>