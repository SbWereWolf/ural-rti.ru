<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;
use SbWereWolf\OrderExport\Agents\OrderExportAgent;
use SbWereWolf\OrderExport\Handlers\OrderHandler;
use SbWereWolf\OrderExport\Internals\OrderExportQueueTable;

defined('B_PROLOG_INCLUDED') || die();

class sbwerewolf_orderexport extends CModule
{
    public $MODULE_ID = 'sbwerewolf.orderexport';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Send order to API';
    public $MODULE_DESCRIPTION = 'Send order to API via local queue';
    public $PARTNER_NAME = 'SbWereWolf';
    public $PARTNER_URI = 'https://example.com';

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.3.0';
        $this->MODULE_VERSION_DATE =
            $arModuleVersion['VERSION_DATE'] ?? '2026-05-28';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->installDB();
        $this->installEvents();
        $this->installAgents();
    }

    public function DoUninstall(): void
    {
        $this->uninstallAgents();
        $this->uninstallEvents();
        $this->uninstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installDB(): void
    {
        require_once __DIR__
            . '/../lib/internals/orderexportqueuetable.php';

        $connection = Application::getConnection();
        $tableName = OrderExportQueueTable::getTableName();

        if (!$connection->isTableExists($tableName)) {
            OrderExportQueueTable::getEntity()->createDbTable();
        }

        $this->installIndexes();
    }

    private function installIndexes(): void
    {
        $connection = Application::getConnection();
        $tableName = OrderExportQueueTable::getTableName();

        if (!$this->indexExists($tableName, 'IX_SBWW_STATUS_ID')) {
            $connection->queryExecute(
                "CREATE INDEX IX_SBWW_STATUS_ID ON {$tableName} (STATUS, ID)"
            );
        }

        if (!$this->indexExists($tableName, 'IX_SBWW_ORDER_ID')) {
            $connection->queryExecute(
                "CREATE INDEX IX_SBWW_ORDER_ID ON {$tableName} (ORDER_ID)"
            );
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $result = $connection->query(sprintf(
            "SHOW INDEX FROM %s WHERE Key_name = '%s'",
            $tableName,
            $sqlHelper->forSql($indexName)
        ));

        return (bool)$result->fetch();
    }

    public function uninstallDB(): void
    {
        require_once __DIR__ . '/../lib/internals/orderexportqueuetable.php';

        $connection = Application::getConnection();
        $tableName = OrderExportQueueTable::getTableName();

        if ($connection->isTableExists($tableName)) {
            $connection->dropTable($tableName);
        }
    }

    public function installEvents(): void
    {
        EventManager::getInstance()->registerEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            OrderHandler::class,
            'onSaleOrderSaved'
        );
    }

    public function uninstallEvents(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            OrderHandler::class,
            'onSaleOrderSaved'
        );
    }

    private function installAgents(): void
    {
        $agentName = '\\' . OrderExportAgent::class . '::run();';

        \CAgent::RemoveAgent($agentName, $this->MODULE_ID);
        \CAgent::AddAgent(
            $agentName,
            $this->MODULE_ID,
            'N',
            60,
            '',
            'Y',
            '',
            100
        );
    }

    private function uninstallAgents(): void
    {
        $agentName = '\\' . OrderExportAgent::class . '::run();';

        \CAgent::RemoveAgent($agentName, $this->MODULE_ID);
    }
}
