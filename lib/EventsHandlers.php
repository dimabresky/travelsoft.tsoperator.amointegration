<?php

namespace travelsoft\amocrm;

use travelsoft\amocrm\tables\TaskQueueTable;

class EventsHandlers {

    /**
     * Обработчик создания заказа в tsoperator.
     *
     * @param int $orderId
     * @return void
     */
    static function onAfterOrderAdd($orderId) {
        TaskQueueTable::add([
            'TYPE' => TaskQueueTable::TYPE_ORDER_LEAD,
            'OBJECT_ID' => (int) $orderId,
        ]);
    }

    /**
     * Обработчик создания элемента в инфоблоке заявок.
     *
     * @param array $arFields
     * @return void
     */
    static function onAfterIBlockElementAdd(&$arFields) {
        
        if (empty($arFields['RESULT'])) {
            return;
        }

        $iblockId = (int) Option::get('CALLBACK_FORM_IBLOCK_ID');
        if (!$iblockId || (int) $arFields['IBLOCK_ID'] !== $iblockId) {
            return;
        }

        $elementId = isset($arFields['ID']) ? (int) $arFields['ID'] : (int) $arFields['RESULT'];
        if (!$elementId) {
            return;
        }

        TaskQueueTable::add([
            'TYPE' => TaskQueueTable::TYPE_CALLBACK_LEAD,
            'OBJECT_ID' => $elementId,
        ]);
    }

}
