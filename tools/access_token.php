<?php

use travelsoft\amocrm\Option;
use AmoCRM\Client\AmoCRMApiClient;

define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once(filter_input(INPUT_SERVER, 'DOCUMENT_ROOT') . '/bitrix/modules/main/include/prolog_before.php');

Bitrix\Main\Loader::includeModule('travelsoft.tsoperator.amointegration');

try {

    if (!$_GET['code'] || $_GET['state'] != $_SESSION['bxamostate']) {
        throw new Exception('empty code or wrong state');
    }

    $apiClient = new AmoCRMApiClient(Option::get('CLIENT_ID'), Option::get('CLIENT_SECRET'), Option::get('REDIRECT_URL'));
    $baseDomain = Option::get('BASE_DOMAIN') . '.amocrm.ru';
    $apiClient->setAccountBaseDomain($baseDomain);
    $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($_GET['code']);

    if (!$accessToken->hasExpired()) {

        Option::set('ACCESS_TOKEN', json_encode([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'expires' => $accessToken->getExpires(),
            'baseDomain' => $apiClient->getAccountBaseDomain(),
            'starttime' => time()
        ]));
    }
    ?>
    <script>
        window.close();
    </script>
    <?

    unset($_SESSION['bxamostate']);
} catch (Exception $ex) {
    echo $ex->getMessage();
    ShowError('Some error!! Close window and try again');
}

