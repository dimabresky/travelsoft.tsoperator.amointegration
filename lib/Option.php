<?php

namespace travelsoft\amocrm;

use Bitrix\Main\Config\Option as BxOption;

/**
 * Option of module
 *
 * @author dimabresky
 */
class Option {

    protected static $mid = "travelsoft.tsoperator.amointegration";

    static function set(string $name, $value) {
        BxOption::set(self::$mid, $name, $value);
    }

    static function get(string $name) {
        return BxOption::get(self::$mid, $name);
    }

}
