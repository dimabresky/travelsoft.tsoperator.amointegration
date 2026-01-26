<?php

namespace travelsoft\amocrm;

use Bitrix\Main\Type\DateTime;
use travelsoft\amocrm\tables\TaskQueueTable;

class EventsHandlers {

    /**
     * Обработчик создания заказа в tsoperator.
     *
     * @param int $orderId
     * @return void
     */
    function onAfterOrderAdd($orderId) {
        TaskQueueTable::add([
            'TYPE' => TaskQueueTable::TYPE_ORDER_LEAD,
            'OBJECT_ID' => (int) $orderId,
        ]);
    }

}
