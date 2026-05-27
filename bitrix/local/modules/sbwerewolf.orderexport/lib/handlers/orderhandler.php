<?php

namespace SbWereWolf\OrderExport\Handlers;

use Bitrix\Main\Event;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use CEventLog;
use SbWereWolf\OrderExport\Service\OrderExportService;
use Throwable;

final class OrderHandler
{
    public static function onSaleOrderSaved(Event $event): void
    {
        if (!Loader::includeModule('sale')) {
            return;
        }

        $order = $event->getParameter('ENTITY');
        $isNew = (bool)$event->getParameter('IS_NEW');

        if (!$order instanceof Order) {
            return;
        }

        try {
            (new OrderExportService())->send($order, $isNew);
        } catch (Throwable $e) {
            CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ORDER_EXPORT_API_FAILED',
                'MODULE_ID' => 'sbwerewolf.orderexport',
                'ITEM_ID' => (string)$order->getId(),
                'DESCRIPTION' => $e->getMessage(),
            ]);
        }
    }
}