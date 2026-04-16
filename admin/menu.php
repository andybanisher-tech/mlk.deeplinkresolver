<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('mlk.deeplinkresolver') >= 'R') {
	$aMenu = [
		'parent_menu' => 'global_menu_settings',
		'sort' => 100,
		'text' => Loc::getMessage('MLK_DL_MENU_TEXT'),
		'title' => Loc::getMessage('MLK_DL_MENU_TITLE'),
		'url' => 'settings.php?mid=mlk.deeplinkresolver&lang='.LANGUAGE_ID,
		'items_id' => 'menu_mlk_deeplinkresolver',
	];
	return $aMenu;
}
return false;
