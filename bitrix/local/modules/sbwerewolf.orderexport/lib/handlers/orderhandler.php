<?php

namespace SbWereWolf\OrderExport\Handlers;

use Bitrix\Main\Event;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Order;
use CEventLog;
use SbWereWolf\OrderExport\Internals\OrderExportQueueTable;
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
            $payload = (new OrderExportService())->buildPayload($order, $isNew);

            $result = OrderExportQueueTable::add([
                'ORDER_ID' => $order->getId(),
                'ACCOUNT_NUMBER' => (string)$order->getField('ACCOUNT_NUMBER'),
                'STATUS' => OrderExportQueueTable::STATUS_NEW,
                'PAYLOAD' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }
        } catch (Throwable $e) {
            CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ORDER_EXPORT_QUEUE_ADD_FAILED',
                'MODULE_ID' => 'sbwerewolf.orderexport',
                'ITEM_ID' => (string)$order->getId(),
                'DESCRIPTION' => $e->getMessage(),
            ]);
        }
    }
}
