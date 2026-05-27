<?php

use Bitrix\Main\Loader;
use SbWereWolf\OrderExport\Agents\OrderExportAgent;
use SbWereWolf\OrderExport\Handlers\OrderHandler;
use SbWereWolf\OrderExport\Internals\OrderExportQueueTable;
use SbWereWolf\OrderExport\Service\OrderExportService;

defined('B_PROLOG_INCLUDED') || die();

Loader::registerAutoLoadClasses('sbwerewolf.orderexport', [
    OrderHandler::class => 'lib/handlers/orderhandler.php',
    OrderExportService::class => 'lib/service/orderexportservice.php',
    OrderExportQueueTable::class => 'lib/internals/orderexportqueuetable.php',
    OrderExportAgent::class => 'lib/agents/orderexportagent.php',
]);
