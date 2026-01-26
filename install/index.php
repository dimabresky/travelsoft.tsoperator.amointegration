<?php

use Bitrix\Main\Localization\Loc,
    Bitrix\Main\ModuleManager,
    Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class travelsoft_tsoperator_amointegration extends CModule {

    public $MODULE_ID = "travelsoft.tsoperator.amointegration";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = "N";
    public $moduleLocation = "bitrix";

    function __construct() {
        $arModuleVersion = array();
        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");
        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
        $this->MODULE_NAME = Loc::getMessage("TRAVELSOFT_AMO_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("TRAVELSOFT_AMO_MODULE_DESC");
        $this->PARTNER_NAME = "travelsoft";
        $this->PARTNER_URI = "https://travelsoft.by";

        if (strpos(__DIR__, 'local/modules') !== false) {
            $this->moduleLocation = "local";
        }
    }

    function DoInstall() {

        global $DB;
        try {

            if (!ModuleManager::isModuleInstalled('travelsoft.travelbooking')) {
                throw new Exception(Loc::getMessage('TRAVELSOFT_AMO_TSOPERATOR_NOT_INSTALLED'));
            }

            ModuleManager::registerModule($this->MODULE_ID);

            Option::set($this->MODULE_ID, 'CLIENT_ID');
            Option::set($this->MODULE_ID, 'CLIENT_SECRET');
            Option::set($this->MODULE_ID, 'REDIRECT_URL', '');
            Option::set($this->MODULE_ID, 'BASE_DOMAIN', '');
            Option::set($this->MODULE_ID, 'ACCESS_TOKEN', '');
            Option::set($this->MODULE_ID, 'LONG_ACCESS_TOKEN', '');
            Option::set($this->MODULE_ID, 'TOUR_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'DATE_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'ADULTS_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'CHILDREN_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'PHONE_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'CID_FIELD_ID', '');
            Option::set($this->MODULE_ID, 'STATUS_ID', '');
            Option::set($this->MODULE_ID, 'PIPELINE_ID', '');

            RegisterModuleDependences("", "TSVOUCHERSOnAfterAdd", $this->MODULE_ID, "\\travelsoft\\amocrm\\EventsHandlers", "onAfterOrderAdd");

            CopyDirFiles(
                    $_SERVER["DOCUMENT_ROOT"] . "/$this->moduleLocation/modules/" . $this->MODULE_ID . "/install/tools",
                    $_SERVER["DOCUMENT_ROOT"] . "/bitrix/tools",
                    true, true
            );

            $sql = "CREATE TABLE IF NOT EXISTS ts_amocrm_integration_leads("
                    . "ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,"
                    . "LEAD_ID INT,"
                    . "ORDER_ID INT,"
                    . "UNIQUE KEY leadid (LEAD_ID),"
                    . "UNIQUE KEY orderid (ORDER_ID)"
                    . ")";

            if (!$DB->Query($sql, true)) {
                throw new Exception(Loc::getMessage('TRAVELSOFT_AMO_CREATE_TABLE_ERROR') . " ts_amocrm_integration_leads");
            }

            $sql = "CREATE TABLE IF NOT EXISTS ts_amocrm_integration_task_queue("
                    . "ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,"
                    . "TYPE VARCHAR(64) NOT NULL,"
                    . "OBJECT_ID INT NOT NULL,"
                    . "DATE_RUN DATETIME"
                    . ")";

            if (!$DB->Query($sql, true)) {
                throw new Exception(Loc::getMessage('TRAVELSOFT_AMO_CREATE_TABLE_ERROR') . " ts_amocrm_integration_task_queue");
            }
        } catch (Exception $ex) {
            $GLOBALS["APPLICATION"]->ThrowException($ex->getMessage());
            $this->DoUninstall();
            return false;
        }

        return true;
    }

    function DoUninstall() {

        global $DB;

        Option::delete($this->MODULE_ID);

        UnRegisterModuleDependences("", "TSVOUCHERSOnAfterAdd", $this->MODULE_ID, "\\travelsoft\\amocrm\\EventsHandlers", "onAfterOrderAdd");

        DeleteDirFiles(
                $_SERVER["DOCUMENT_ROOT"] . "/$this->moduleLocation/modules/" . $this->MODULE_ID . "/install/tools",
                $_SERVER["DOCUMENT_ROOT"] . "/bitrix/tools"
        );

        $DB->Query('DROP TABLE IF EXISTS ts_amocrm_integration_leads');
        $DB->Query('DROP TABLE IF EXISTS ts_amocrm_integration_task_queue');

        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

}
