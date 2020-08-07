<?php

namespace travelsoft\amocrm\tables;

use Bitrix\Main\Entity;

/**
 * Table Leads
 *
 * @author dimabresky
 */
class LeadsTable extends Entity\DataManager {

    public static function getTableName() {
        return 'ts_amocrm_integration_leads';
    }

    public static function getMap() {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => true
                    )),
            new Entity\IntegerField('LEAD_ID', array(
                'required' => true
                    )),
            new Entity\IntegerField('ORDER_ID', array(
                'required' => true
                    )),
        );
    }

}
