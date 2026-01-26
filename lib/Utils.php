<?php

namespace travelsoft\amocrm;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Description of Utils
 *
 * @author dimabresky
 */
class Utils {

    /**
     * Устанавливает токен доступа для клиента amoCRM.
     *
     * Берёт токен из параметров модуля. Если ACCESS_TOKEN отсутствует,
     * использует долгоживущий токен LONG_ACCESS_TOKEN.
     *
     * @param AmoCRMApiClient $apiClient
     * @return void
     */
    public static function applyAccessToken(AmoCRMApiClient $apiClient): void {
        $accessTokenJson = (string) Option::get('ACCESS_TOKEN');

        if ($accessTokenJson !== '') {
            $accessTokenData = (array) json_decode($accessTokenJson, true);

            if (!empty($accessTokenData['accessToken'])) {
                $apiClient->setAccessToken(new AccessToken([
                    'access_token' => $accessTokenData['accessToken'],
                    'refresh_token' => $accessTokenData['refreshToken'] ?? null,
                    'expires' => $accessTokenData['expires'] ?? null,
                    'baseDomain' => $accessTokenData['baseDomain'] ?? null,
                ]));
                return;
            }
        }

        $longAccessToken = trim((string) Option::get('LONG_ACCESS_TOKEN'));
        if ($longAccessToken !== '') {
            $apiClient->setAccessToken(new LongLivedAccessToken($longAccessToken));
        }
    }
}
