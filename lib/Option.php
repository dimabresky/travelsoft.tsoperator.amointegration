<?php

namespace travelsoft\amocrm;

use Bitrix\Main\Config\Option as BxOption;

/**
 * Option of module
 *
 * @author dimabresky
 */
class Option extends BxOption {

    protected static $mid = "travelsoft.tsoperator.amointegration";

    static function set(string $name, $value) {
        parent::set(self::$mid, $name, $value);
    }

    static function get(string $name) {
        return parent::get(self::$mid, $name);
    }

}
