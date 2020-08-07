<?php

if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/modules/travelsoft.tsoperator.amointegration/tools/access_token.php')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/travelsoft.tsoperator.amointegration/tools/access_token.php';
} else {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/travelsoft.tsoperator.amointegration/tools/access_token.php';
}