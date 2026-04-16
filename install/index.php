<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

class mlk_deeplinkresolver extends CModule
{
	public $MODULE_ID = 'mlk.deeplinkresolver';
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
		$this->PARTNER_NAME = Loc::getMessage('MLK_DL_PARTNER_NAME');
		$this->PARTNER_URI = 'https://mlk.company';
	}

	public function DoInstall()
	{
		global $APPLICATION;
		if (!$this->checkRequirements()) {
			$APPLICATION->ThrowException(Loc::getMessage('MLK_DL_REQUIREMENTS_FAILED'));
			return false;
		}
		ModuleManager::registerModule($this->MODULE_ID);
		$this->installDB();
		$this->installFiles();
		$this->installEvents();
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
		$this->uninstallEvents();
		ModuleManager::unRegisterModule($this->MODULE_ID);
		return true;
	}

	private function checkRequirements()
	{
		if (!Loader::includeModule('main')) {
			return false;
		}
		if (!Loader::includeModule('iblock')) {
			return false;
		}
		if (defined('SM_VERSION') && version_compare(SM_VERSION, '26.150.0', '<')) {
			return false;
		}
		return true;
	}

	public function installDB()
	{
		Loader::includeModule($this->MODULE_ID);
		$className = '\Mlk\DeepLinkResolver\Resolver\RuleTable';
		if (class_exists($className)) {
			$connection = $className::getEntity()->getConnection();
			$tableName = $className::getTableName();
			$oldTableName = 'mlk_appdeeplink_resolver_rule';
			if ($connection->isTableExists($oldTableName)) {
				$connection->renameTable($oldTableName, $tableName);
			}
			if (!$connection->isTableExists($tableName)) {
				$className::getEntity()->createDbTable();
			} else {
				$sql = $connection->query('SHOW COLUMNS FROM ' . $tableName);
				$columns = [];
				while ($row = $sql->fetch()) {
					$columns[] = $row['Field'];
				}
				if (in_array('URL_PATTERN', $columns) && !in_array('URL_TEMPLATE', $columns)) {
					$connection->queryExecute("ALTER TABLE {$tableName} ADD COLUMN URL_TEMPLATE text NULL");
					$connection->queryExecute("UPDATE {$tableName} SET URL_TEMPLATE = URL_PATTERN WHERE URL_TEMPLATE IS NULL");
				}
				if (!in_array('PLACEHOLDER_MAPPING', $columns)) {
					$connection->queryExecute("ALTER TABLE {$tableName} ADD COLUMN PLACEHOLDER_MAPPING text NULL");
					$connection->queryExecute("UPDATE {$tableName} SET PLACEHOLDER_MAPPING = '{}' WHERE PLACEHOLDER_MAPPING IS NULL");
				}
			}
		}
		Option::set($this->MODULE_ID, 'db_version', $this->MODULE_VERSION);
	}

	public function uninstallDB()
	{
		Loader::includeModule($this->MODULE_ID);
		$className = '\Mlk\DeepLinkResolver\Resolver\RuleTable';
		if (class_exists($className)) {
			$connection = $className::getEntity()->getConnection();
			$tableName = $className::getTableName();
			if ($connection->isTableExists($tableName)) {
				$connection->dropTable($tableName);
			}
		}
		Option::delete($this->MODULE_ID);
	}

	public function installFiles()
	{
		CopyDirFiles(
			__DIR__ . '/../admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
			true,
			true
		);
		CopyDirFiles(
			__DIR__ . '/../tools',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID,
			true,
			true
		);
	}

	public function uninstallFiles()
	{
		DeleteDirFiles(__DIR__ . '/../admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
		$toolsPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID;
		DeleteDirFiles($toolsPath, $toolsPath);
		@rmdir($toolsPath);
	}

	public function installEvents()
	{
	}

	public function uninstallEvents()
	{
	}
}
