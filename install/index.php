<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Mlk\AppDeepLinkResolver\Resolver\RuleTable;

Loc::loadMessages(__FILE__);

class mlk_appdeeplinkresolver extends CModule
{
    public $MODULE_ID = 'mlk.appdeeplinkresolver';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MLK_DL_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MLK_DL_MODULE_DESC');
        $this->PARTNER_NAME = 'mlk';
        $this->PARTNER_URI = 'https://www.mirlk.ru';
    }

    public function DoInstall()
{
    $this->InstallFiles();
    $this->InstallDB();
      ModuleManager::registerModule($this->MODULE_ID);
    return true;
}

    public function DoUninstall()
    {
        global $APPLICATION;
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        if ($request->get('savedata') !== 'Y') {
            $this->uninstallDB();
        }
        $this->uninstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    private function checkRequirements()
    {
        if (!Loader::includeModule('main')) return false;
        if (!Loader::includeModule('iblock')) return false;
        if (version_compare(SM_VERSION, '20.0.0', '<')) return false; // минимальная версия
        return true;
    }

    private function installDB()
    {
        Loader::includeModule($this->MODULE_ID);
        if (!RuleTable::getEntity()->getConnection()->isTableExists(RuleTable::getTableName())) {
            RuleTable::getEntity()->createDbTable();
        }
        Option::set($this->MODULE_ID, 'db_version', $this->MODULE_VERSION);
    }

    private function uninstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        $connection = RuleTable::getEntity()->getConnection();
        if ($connection->isTableExists(RuleTable::getTableName())) {
            $connection->dropTable(RuleTable::getTableName());
        }
        Option::delete($this->MODULE_ID);
    }

    private function installFiles()
    {
        CopyDirFiles(__DIR__ . '/../admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
        CopyDirFiles(__DIR__ . '/../tools', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID, true, true);
    }

    private function uninstallFiles()
    {
        DeleteDirFiles(__DIR__ . '/../admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        DeleteDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID, $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID);
        @rmdir($_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID);
    }
}
?>