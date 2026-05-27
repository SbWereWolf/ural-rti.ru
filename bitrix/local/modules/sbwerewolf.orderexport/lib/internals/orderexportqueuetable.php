<?php

namespace SbWereWolf\OrderExport\Internals;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\Type\DateTime;

final class OrderExportQueueTable extends DataManager
{
    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    public static function getTableName(): string
    {
        return 'b_sbwerewolf_orderexport_queue';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),

            new IntegerField('ORDER_ID', [
                'required' => true,
            ]),

            new StringField('ACCOUNT_NUMBER', [
                'size' => 64,
            ]),

            new StringField('STATUS', [
                'required' => true,
                'size' => 32,
                'default_value' => self::STATUS_NEW,
            ]),

            new TextField('PAYLOAD', [
                'required' => true,
            ]),

            new IntegerField('HTTP_STATUS'),

            new TextField('LAST_ERROR'),

            new DatetimeField('DATE_CREATE', [
                'default_value' => static fn () => new DateTime(),
            ]),

            new DatetimeField('DATE_UPDATE', [
                'default_value' => static fn () => new DateTime(),
            ]),
        ];
    }
}
