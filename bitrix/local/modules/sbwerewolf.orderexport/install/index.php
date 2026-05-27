<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;
use SbWereWolf\OrderExport\Handlers\OrderHandler;

defined('B_PROLOG_INCLUDED') || die();

class sbwerewolf_orderexport extends CModule
{
    public $MODULE_ID = 'sbwerewolf.orderexport';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Send order to API';
    public $MODULE_DESCRIPTION = 'Send order to API';
    public $PARTNER_NAME = 'SbWereWolf';
    public $PARTNER_URI = 'https://example.com';

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '2026-05-28';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);

        EventManager::getInstance()->registerEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            OrderHandler::class,
            'onSaleOrderSaved'
        );
    }

    public function DoUninstall(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            OrderHandler::class,
            'onSaleOrderSaved'
        );

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}