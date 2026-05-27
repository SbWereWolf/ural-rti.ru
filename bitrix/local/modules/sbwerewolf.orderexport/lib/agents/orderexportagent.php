<?php

namespace SbWereWolf\OrderExport\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use CEventLog;
use RuntimeException;
use SbWereWolf\OrderExport\Internals\OrderExportQueueTable;
use SbWereWolf\OrderExport\Service\OrderExportService;
use Throwable;

final class OrderExportAgent
{
    private const PROCESS_LIMIT = 10;
    private const PROCESSING_TIMEOUT_SECONDS = 60 * 15;

    public static function run(): string
    {
        try {
            self::processQueue();
        } catch (Throwable $e) {
            CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ORDER_EXPORT_AGENT_FAILED',
                'MODULE_ID' => 'sbwerewolf.orderexport',
                'DESCRIPTION' => $e->getMessage(),
            ]);
        }

        return '\\' . self::class . '::run();';
    }

    private static function processQueue(): void
    {
        self::markStaleProcessingRowsAsError();

        $rows = self::claimBatch(self::PROCESS_LIMIT);

        if (!$rows) {
            return;
        }

        $service = new OrderExportService();

        foreach ($rows as $row) {
            try {
                $payload = Json::decode((string)$row['PAYLOAD']);

                if (!is_array($payload)) {
                    throw new RuntimeException('Queue payload is not an array');
                }

                $httpStatus = $service->sendPayload($payload);
                self::markDone((int)$row['ID'], $httpStatus);
            } catch (Throwable $e) {
                self::markError($row, $e);
            }
        }
    }

    private static function claimBatch(int $limit): array
    {
        $connection = Application::getConnection();
        $tableName = OrderExportQueueTable::getTableName();

        $connection->startTransaction();

        try {
            $sql = "
                SELECT
                    ID,
                    ORDER_ID,
                    ACCOUNT_NUMBER,
                    PAYLOAD
                FROM {$tableName}
                WHERE STATUS = '" . OrderExportQueueTable::STATUS_NEW . "'
                ORDER BY ID
                LIMIT {$limit}
                FOR UPDATE SKIP LOCKED
            ";

            $result = $connection->query($sql);
            $rows = [];
            $ids = [];

            while ($row = $result->fetch()) {
                $row['ID'] = (int)$row['ID'];
                $row['ORDER_ID'] = (int)$row['ORDER_ID'];

                $rows[] = $row;
                $ids[] = $row['ID'];
            }

            if ($ids) {
                $idsSql = implode(',', array_map('intval', $ids));

                $connection->queryExecute(
                    "
                    UPDATE {$tableName}
                    SET
                        STATUS = '" . OrderExportQueueTable::STATUS_PROCESSING . "',
                        DATE_UPDATE = NOW()
                    WHERE ID IN ({$idsSql})
                "
                );
            }

            $connection->commitTransaction();

            return $rows;
        } catch (Throwable $e) {
            $connection->rollbackTransaction();
            throw $e;
        }
    }

    private static function markDone(int $id, int $httpStatus): void
    {
        OrderExportQueueTable::update($id, [
            'STATUS' => OrderExportQueueTable::STATUS_DONE,
            'HTTP_STATUS' => $httpStatus,
            'LAST_ERROR' => null,
            'DATE_UPDATE' => new DateTime(),
        ]);
    }

    private static function markError(array $row, Throwable $e): void
    {
        $httpStatus = $e->getCode() > 0 ? $e->getCode() : null;

        OrderExportQueueTable::update((int)$row['ID'], [
            'STATUS' => OrderExportQueueTable::STATUS_ERROR,
            'HTTP_STATUS' => $httpStatus,
            'LAST_ERROR' => $e->getMessage(),
            'DATE_UPDATE' => new DateTime(),
        ]);

        CEventLog::Add([
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => 'ORDER_EXPORT_SEND_FAILED',
            'MODULE_ID' => 'sbwerewolf.orderexport',
            'ITEM_ID' => (string)($row['ORDER_ID'] ?? ''),
            'DESCRIPTION' => $e->getMessage(),
        ]);
    }

    private static function markStaleProcessingRowsAsError(): void
    {
        $connection = Application::getConnection();
        $tableName = OrderExportQueueTable::getTableName();
        $message = $connection->getSqlHelper()
            ->forSql('Processing timeout, marked as error');
        $timeout = self::PROCESSING_TIMEOUT_SECONDS;

        $connection->queryExecute(
            "
            UPDATE {$tableName}
            SET
                STATUS = '" . OrderExportQueueTable::STATUS_ERROR . "',
                LAST_ERROR = '{$message}',
                DATE_UPDATE = NOW()
            WHERE
                STATUS = '" . OrderExportQueueTable::STATUS_PROCESSING . "'
                AND DATE_UPDATE < DATE_SUB(NOW(), INTERVAL {$timeout} SECOND)
        "
        );
    }
}
