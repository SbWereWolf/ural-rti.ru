<?php

namespace SbWereWolf\OrderExport\Service;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Order;
use RuntimeException;

final class OrderExportService
{
    private const API_URL = 'https://example.com/api';
    private const API_TOKEN = 'YOUR_SECRET_TOKEN';

    public function send(Order $order, bool $isNew): void
    {
        $payload = $this->buildPayload($order, $isNew);

        $http = new HttpClient();

        $http->setTimeout(2);
        $http->setStreamTimeout(2);

        $http->setHeader('Content-Type', 'application/json');
        $http->setHeader('Accept', 'application/json');
        $http->setHeader(
            'Authorization',
            'Bearer ' . self::API_TOKEN,
        );

        $response = $http->post(
            self::API_URL,
            Json::encode($payload, JSON_UNESCAPED_UNICODE)
        );

        $status = $http->getStatus();

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                sprintf(
                    'API error. HTTP status: %s. Response: %s',
                    $status ?: 'no status',
                    is_string($response) ? $response : 'empty response'
                )
            );
        }
    }

    private function buildPayload(Order $order, bool $isNew): array
    {
        $result =  [
            'id' => $order->getId(),
            'account_number' => $order->getField('ACCOUNT_NUMBER'),
            'is_new' => $isNew,
            'user_id' => $order->getUserId(),
            'status_id' => $order->getField('STATUS_ID'),
            'price' => $order->getPrice(),
            'currency' => $order->getCurrency(),
            'properties' => $this->getProperties($order),
            'basket' => $this->getBasketItems($order),
        ];

        return $result;
    }

    private function getProperties(Order $order): array
    {
        $result = [];

        foreach ($order->getPropertyCollection() as $property) {
            $code = (string)$property->getField('CODE');

            if ($code !== '') {
                $result[$code] = $property->getValue();
            }
        }

        return $result;
    }

    private function getBasketItems(Order $order): array
    {
        $result = [];

        foreach ($order->getBasket() as $basketItem) {
            $result[] = [
                'product_id' => $basketItem->getProductId(),
                'name' => $basketItem->getField('NAME'),
                'quantity' => $basketItem->getQuantity(),
                'price' => $basketItem->getPrice(),
                'currency' => $basketItem->getCurrency(),
            ];
        }

        return $result;
    }
}