<?php

namespace travelsoft\amocrm;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\DateCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\DateCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use League\OAuth2\Client\Token\AccessToken;
use travelsoft\booking\Logger;
use travelsoft\booking\stores\Users;
use travelsoft\booking\stores\Vouchers;

/**
 * Description of Utils
 *
 * @author dimabresky
 */
class Utils {

    /**
     * Создаёт и настраивает клиента amoCRM.
     *
     * @return AmoCRMApiClient
     */
    private static function initApiClient(): AmoCRMApiClient {
        $apiClient = new AmoCRMApiClient(
            Option::get('CLIENT_ID'),
            Option::get('CLIENT_SECRET'),
            Option::get('REDIRECT_URL')
        );

        $baseDomain = Option::get('BASE_DOMAIN') . '.amocrm.ru';
        $apiClient->setAccountBaseDomain($baseDomain);
        self::applyAccessToken($apiClient);

        return $apiClient;
    }

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

    /**
     * Создаёт лид в amoCRM после создания заказа в tsoperator.
     *
     * @param int $orderId
     * @return void
     */
    public static function enqueueOrderLeadTask($orderId): void {
        \Bitrix\Main\Loader::includeModule('travelsoft.travelbooking');

        $arOrder = Vouchers::getById($orderId);

        if (!empty($arOrder) && $arOrder['ID']) {
            $client = Users::getById($arOrder['UF_CLIENT'], ['*', 'UF_*']);

            $apiClient = self::initApiClient();

            $lead = new LeadModel();
            $book = current($arOrder['BOOKINGS']);

            $lead->setName($book['UF_SERVICE_NAME']);
            $lead->setPrice(ceil($arOrder['SEPARATED_COSTS']['TOTAL_COST']));
            $lead->setCreatedBy(0);
            $lead->setStatusId(Option::get('STATUS_ID'));
            $lead->setPipelineId(Option::get('PIPELINE_ID'));

            $leadCustomFieldsValues = new CustomFieldsValuesCollection();

            # tour name
            $tourCustomFieldValueModel = new TextCustomFieldValuesModel();
            $tourCustomFieldValueModel->setFieldId(Option::get('TOUR_FIELD_ID'));
            $tourCustomFieldValueModel->setValues(
                (new TextCustomFieldValueCollection())
                    ->add((new TextCustomFieldValueModel())->setValue($book['UF_SERVICE_NAME']))
            );
            $leadCustomFieldsValues->add($tourCustomFieldValueModel);

            # date
            $dateCustomFieldValueModel = new DateCustomFieldValuesModel();
            $dateCustomFieldValueModel->setFieldId(Option::get('DATE_FIELD_ID'));
            $dateCustomFieldValueModel->setValues(
                (new DateCustomFieldValueCollection())
                    ->add((new DateCustomFieldValueModel())->setValue($book['UF_DATE_FROM']->getTimestamp()))
            );
            $leadCustomFieldsValues->add($dateCustomFieldValueModel);

            # adults
            $adultsCustomFieldValueModel = new NumericCustomFieldValuesModel();
            $adultsCustomFieldValueModel->setFieldId(Option::get('ADULTS_FIELD_ID'));
            $adultsCustomFieldValueModel->setValues(
                (new NumericCustomFieldValueCollection())
                    ->add((new NumericCustomFieldValueModel())->setValue($book['UF_ADULTS']))
            );
            $leadCustomFieldsValues->add($adultsCustomFieldValueModel);

            #children
            if ($book['UF_CHILDREN']) {
                $childrenCustomFieldValueModel = new NumericCustomFieldValuesModel();
                $childrenCustomFieldValueModel->setFieldId(Option::get('CHILDREN_FIELD_ID'));
                $childrenCustomFieldValueModel->setValues(
                    (new NumericCustomFieldValueCollection())
                        ->add((new NumericCustomFieldValueModel())->setValue($book['UF_CHIDLREN']))
                );
                $leadCustomFieldsValues->add($childrenCustomFieldValueModel);
            }

            if ($client['PERSONAL_PHONE']) {
                $phoneCustomFieldValueModel = new TextCustomFieldValuesModel();
                $phoneCustomFieldValueModel->setFieldId(Option::get('PHONE_FIELD_ID'));
                $phoneCustomFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue($client['PERSONAL_PHONE']))
                );
                $leadCustomFieldsValues->add($phoneCustomFieldValueModel);
            }
            if ($client['UF_CID']) {
                $cidCustomFieldValueModel = new TextCustomFieldValuesModel();
                $cidCustomFieldValueModel->setFieldId(Option::get('CID_FIELD_ID'));
                $cidCustomFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue($client['UF_CID']))
                );
                $leadCustomFieldsValues->add($cidCustomFieldValueModel);
            }

            $lead->setCustomFieldsValues($leadCustomFieldsValues);

            $clientName = trim((string) ($client['FULL_NAME'] ?? ''));
            $clientEmail = trim((string) ($client['EMAIL'] ?? ''));
            $clientPhone = trim((string) ($client['PERSONAL_PHONE'] ?? ''));

            $hasContactData = ($clientName !== '' || $clientEmail !== '' || $clientPhone !== '');
            if ($hasContactData) {
                $contact = new ContactModel();
                if ($clientName !== '') {
                    $contact->setName($clientName);
                } elseif ($clientEmail !== '') {
                    $contact->setName($clientEmail);
                }

                $contactFields = new CustomFieldsValuesCollection();
                if ($clientEmail !== '') {
                    $emailField = new MultitextCustomFieldValuesModel();
                    $emailField->setFieldCode('EMAIL');
                    $emailField->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add((new MultitextCustomFieldValueModel())->setValue($clientEmail)->setEnum('WORK'))
                    );
                    $contactFields->add($emailField);
                }
                if ($clientPhone !== '') {
                    $phoneField = new MultitextCustomFieldValuesModel();
                    $phoneField->setFieldCode('PHONE');
                    $phoneField->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add((new MultitextCustomFieldValueModel())->setValue($clientPhone)->setEnum('WORK'))
                    );
                    $contactFields->add($phoneField);
                }
                if ($contactFields->count() > 0) {
                    $contact->setCustomFieldsValues($contactFields);
                }

                $contacts = new ContactsCollection();
                $contacts->add($contact);
                $lead->setContacts($contacts);
            }

            $leadsService = $apiClient->leads();
            try {
                $lead = $leadsService->addOneComplex($lead);

                tables\LeadsTable::add([
                    'LEAD_ID' => $lead->getId(),
                    'ORDER_ID' => $arOrder['ID']
                ]);
            } catch (AmoCRMApiException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y') . '.txt'))
                    ->write($e->getMessage());
            }
        }
    }

    /**
     * Создаёт сделку и контакт в amoCRM по элементу инфоблока заявок.
     *
     * @param int $elementId
     * @return void
     */
    public static function createLeadAndContactFromIblockElement($elementId): void {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return;
        }

        $elementId = (int) $elementId;
        if ($elementId <= 0) {
            return;
        }

        $element = \CIBlockElement::GetList(
            array(),
            array('ID' => $elementId),
            false,
            false,
            array(
                'ID',
                'IBLOCK_ID',
                'NAME',
                'PROPERTY_USER_NAME',
                'PROPERTY_EMAIL',
                'PROPERTY_USER_PHONE',
                'PROPERTY_CURRENT_PAGE',
            )
        )->GetNext();

        if (!$element) {
            return;
        }
        
        $userName = trim((string) ($element['PROPERTY_USER_NAME_VALUE'] ?? ''));
        $email = trim((string) ($element['PROPERTY_EMAIL_VALUE'] ?? ''));
        $phone = trim((string) ($element['PROPERTY_USER_PHONE_VALUE'] ?? ''));
        $currentPage = trim((string) ($element['PROPERTY_CURRENT_PAGE_VALUE'] ?? ''));

        $apiClient = self::initApiClient();

        $contact = new ContactModel();
        if ($userName !== '') {
            $contact->setName($userName);
        } elseif ($email !== '') {
            $contact->setName($email);
        } elseif ($phone !== '') {
            $contact->setName($phone);
        }

        $contactFields = new CustomFieldsValuesCollection();
        if ($email !== '') {
            $emailField = new MultitextCustomFieldValuesModel();
            $emailField->setFieldCode('EMAIL');
            $emailField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add((new MultitextCustomFieldValueModel())->setValue($email)->setEnum('WORK'))
            );
            $contactFields->add($emailField);
        }
        if ($phone !== '') {
            
            $phoneField = new MultitextCustomFieldValuesModel();
            $phoneField->setFieldCode('PHONE');
            $phoneField->setValues(
                (new MultitextCustomFieldValueCollection())
                    ->add((new MultitextCustomFieldValueModel())->setValue($phone)->setEnum('WORKDD'))
            );
            $contactFields->add($phoneField);
        }
        if ($contactFields->count() > 0) {
            $contact->setCustomFieldsValues($contactFields);
        }

        $lead = new LeadModel();
        $leadName = 'Нужна консультация';
        $lead->setName($leadName);
        if ($currentPage !== '') {
            $tourLinkFieldId = (int) Option::get('TOUR_LINK_FIELD_ID');
            if ($tourLinkFieldId > 0) {
                $tourLinkFieldValueModel = new TextCustomFieldValuesModel();
                $tourLinkFieldValueModel->setFieldId($tourLinkFieldId);
                $tourLinkFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue($currentPage))
                );
                $leadCustomFieldsValues = $lead->getCustomFieldsValues() ?: new CustomFieldsValuesCollection();
                $leadCustomFieldsValues->add($tourLinkFieldValueModel);
                $lead->setCustomFieldsValues($leadCustomFieldsValues);
            }
        }
        $lead->setStatusId(Option::get('STATUS_ID'));
        $lead->setPipelineId(Option::get('PIPELINE_ID'));

        $contacts = new ContactsCollection();
        $contacts->add($contact);
        $lead->setContacts($contacts);

        try {
            $apiClient->leads()->addOneComplex($lead);
        } catch (AmoCRMApiException $e) {
            (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y') . '.txt'))
                ->write($e->getMessage());
        }
    }
}
