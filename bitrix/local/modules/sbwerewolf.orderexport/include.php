<?php

use Bitrix\Main\Loader;
use SbWereWolf\OrderExport\Handlers\OrderHandler;
use SbWereWolf\OrderExport\Service\OrderExportService;

defined('B_PROLOG_INCLUDED') || die();

Loader::registerAutoLoadClasses('sbwerewolf.orderexport', [
    OrderHandler::class => 'lib/handlers/orderhandler.php',
    OrderExportService::class => 'lib/service/orderexportservice.php',
]);