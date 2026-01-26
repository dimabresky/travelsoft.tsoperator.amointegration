<?php

namespace travelsoft\amocrm\tables;

use Bitrix\Main\Entity;

/**
 * ORM table for delayed lead creation tasks.
 *
 * @author dimabresky
 */
class TaskQueueTable extends Entity\DataManager {

    public const TYPE_ORDER_LEAD = 'order_lead';
    public const TYPE_CALLBACK_LEAD = 'callback_lead';

    /**
     * Returns DB table name.
     *
     * @return string
     */
    public static function getTableName() {
        return 'ts_amocrm_integration_task_queue';
    }

    /**
     * Returns table fields map.
     *
     * @return array
     */
    public static function getMap() {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => true,
            )),
            new Entity\StringField('TYPE', array(
                'required' => true,
            )),
            new Entity\IntegerField('OBJECT_ID', array(
                'required' => true,
            )),
            new Entity\DatetimeField('DATE_RUN'),
        );
    }
}
