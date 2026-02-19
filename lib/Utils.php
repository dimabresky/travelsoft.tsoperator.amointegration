<?php

namespace travelsoft\amocrm;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Filters\ContactsFilter;
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
use travelsoft\booking\stores\Placements;
use travelsoft\booking\stores\Rooms;
use travelsoft\booking\stores\Users;
use travelsoft\booking\stores\Vouchers;
use travelsoft\booking\adapters\User;

/**
 * Description of Utils
 *
 * @author dimabresky
 */
class Utils
{



    /**
     * Создаёт и настраивает клиента amoCRM.
     *
     * @return AmoCRMApiClient
     */
    private static function initApiClient(): AmoCRMApiClient
    {
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
    public static function applyAccessToken(AmoCRMApiClient $apiClient): void
    {
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
     * Оставляет в номере телефона только цифры.
     *
     * Удаляет все символы, кроме цифр (пробелы, скобки, дефисы и т.д.).
     *
     * @param string $phone Номер телефона
     * @return string Номер телефона, содержащий только цифры
     */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Ищет контакт по номеру телефона и/или email через API amoCRM.
     * При нахождении привязывает его к сделке, при отсутствии — создаёт новый контакт и привязывает.
     *
     * @param AmoCRMApiClient $apiClient
     * @param LeadModel $lead
     * @param string $phone Нормализованный номер телефона (только цифры)
     * @param string $contactName Имя контакта
     * @param string $contactEmail Email контакта
     * @return void
     */
    private static function findOrCreateContactAndLinkToLead(
        AmoCRMApiClient $apiClient,
        LeadModel $lead,
        string $phone,
        string $contactName,
        string $contactEmail
    ): void {
        if ($phone === '' && $contactEmail === '') {
            return;
        }

        $contact = null;

        // Поиск контакта по номеру телефона через API
        if ($phone !== '') {
            $filter = new ContactsFilter();
            $filter->setQuery($phone);

            try {
                $contacts = $apiClient->contacts()->get($filter);
                if ($contacts->count() > 0) {
                    $contact = $contacts->first();
                }
            } catch (AmoCRMApiException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                    ->write('Contact search by phone failed: ' . $e->getMessage());
            }
        }

        // Если не найден по телефону — поиск по email
        if ($contact === null && $contactEmail !== '') {
            $filter = new ContactsFilter();
            $filter->setQuery($contactEmail);

            try {
                $contacts = $apiClient->contacts()->get($filter);
                if ($contacts->count() > 0) {
                    $contact = $contacts->first();
                }
            } catch (AmoCRMApiException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                    ->write('Contact search by email failed: ' . $e->getMessage());
            }
        }

        // Если контакт не найден — создаём новый
        if ($contact === null) {
            $contact = new ContactModel();
            if ($contactName !== '') {
                $contact->setName($contactName);
            } elseif ($contactEmail !== '') {
                $contact->setName($contactEmail);
            } else {
                $contact->setName($phone);
            }

            $contactFields = new CustomFieldsValuesCollection();
            if ($contactEmail !== '') {
                $emailField = new MultitextCustomFieldValuesModel();
                $emailField->setFieldCode('EMAIL');
                $emailField->setValues(
                    (new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())->setValue($contactEmail)->setEnum('WORK'))
                );
                $contactFields->add($emailField);
            }
            if ($phone !== '') {
                $phoneField = new MultitextCustomFieldValuesModel();
                $phoneField->setFieldCode('PHONE');
                $phoneField->setValues(
                    (new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())->setValue($phone)->setEnum('WORK'))
                );
                $contactFields->add($phoneField);
            }
            if ($contactFields->count() > 0) {
                $contact->setCustomFieldsValues($contactFields);
            }

            try {
                $contact = $apiClient->contacts()->addOne($contact);
            } catch (AmoCRMApiException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                    ->write('Contact creation failed: ' . $e->getMessage());
                return;
            }
        }

        // Привязка контакта к сделке
        try {
            $links = new LinksCollection();
            $links->add($contact);
            $apiClient->leads()->link($lead, $links);
        } catch (AmoCRMApiException $e) {
            (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                ->write('Contact link to lead failed: ' . $e->getMessage());
        }
    }

    /**
     * Создаёт лид в amoCRM после создания заказа в tsoperator.
     *
     * @param int $orderId
     * @return void
     */
    public static function enqueueOrderLeadTask($orderId): void
    {
        \Bitrix\Main\Loader::includeModule('travelsoft.travelbooking');

        $arOrder = Vouchers::getById($orderId);

        if (!empty($arOrder) && $arOrder['ID']) {
            // Если заказ помечен как не отправлять в CRM, выходим
            if ((int)($arOrder['UF_NOT_SEND_TO_CRM'] ?? 0) === 1) {
                return;
            }

            $client = Users::getById($arOrder['UF_CLIENT'], ['*', 'UF_*']);

            $isAgent = User::isAgentById($client['ID']);

            $apiClient = self::initApiClient();

            $lead = new LeadModel();
            $book = current($arOrder['BOOKINGS']);

            $lead->setName($book['UF_SERVICE_NAME']);
            $lead->setPrice(ceil($arOrder['SEPARATED_COSTS']['TOURPRODUCT_TO_PAY']));
            $lead->setCreatedBy(0);
            $lead->setStatusId(Option::get('STATUS_ID'));
            $lead->setPipelineId(Option::get('PIPELINE_ID'));

            $leadCustomFieldsValues = new CustomFieldsValuesCollection();

            # tour name
            $tourFieldId = (int) Option::get('TOUR_FIELD_ID');
            if ($tourFieldId > 0) {
                $tourCustomFieldValueModel = new TextCustomFieldValuesModel();
                $tourCustomFieldValueModel->setFieldId($tourFieldId);
                $tourCustomFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue($book['UF_SERVICE_NAME']))
                );
                $leadCustomFieldsValues->add($tourCustomFieldValueModel);
            }

            # date
            $dateFieldId = (int) Option::get('DATE_FIELD_ID');
            if ($dateFieldId > 0) {
                $dateCustomFieldValueModel = new DateCustomFieldValuesModel();
                $dateCustomFieldValueModel->setFieldId($dateFieldId);
                $dateCustomFieldValueModel->setValues(
                    (new DateCustomFieldValueCollection())
                        ->add((new DateCustomFieldValueModel())->setValue($book['UF_DATE_FROM']->getTimestamp()))
                );
                $leadCustomFieldsValues->add($dateCustomFieldValueModel);
            }

            $dateEndValue = null;
            if (!empty($book['UF_DATE_TO'])) {
                if (is_object($book['UF_DATE_TO']) && method_exists($book['UF_DATE_TO'], 'getTimestamp')) {
                    $dateEndValue = $book['UF_DATE_TO']->getTimestamp();
                } elseif (is_string($book['UF_DATE_TO'])) {
                    $parsedDateEnd = strtotime($book['UF_DATE_TO']);
                    if ($parsedDateEnd !== false) {
                        $dateEndValue = $parsedDateEnd;
                    }
                }
            }
            if ($dateEndValue !== null) {
                $dateEndFieldId = (int) Option::get('DATE_END_FIELD_ID');
                if ($dateEndFieldId > 0) {
                    $dateEndCustomFieldValueModel = new DateCustomFieldValuesModel();
                    $dateEndCustomFieldValueModel->setFieldId($dateEndFieldId);
                    $dateEndCustomFieldValueModel->setValues(
                        (new DateCustomFieldValueCollection())
                            ->add((new DateCustomFieldValueModel())->setValue($dateEndValue))
                    );
                    $leadCustomFieldsValues->add($dateEndCustomFieldValueModel);
                }
            }

            # adults
            $adultsFieldId = (int) Option::get('ADULTS_FIELD_ID');
            if ($adultsFieldId > 0) {
                $adultsCustomFieldValueModel = new NumericCustomFieldValuesModel();
                $adultsCustomFieldValueModel->setFieldId($adultsFieldId);
                $adultsCustomFieldValueModel->setValues(
                    (new NumericCustomFieldValueCollection())
                        ->add((new NumericCustomFieldValueModel())->setValue($book['UF_ADULTS']))
                );
                $leadCustomFieldsValues->add($adultsCustomFieldValueModel);
            }

            #children
            if ($book['UF_CHILDREN']) {
                $childrenFieldId = (int) Option::get('CHILDREN_FIELD_ID');
                if ($childrenFieldId > 0) {
                    $childrenCustomFieldValueModel = new NumericCustomFieldValuesModel();
                    $childrenCustomFieldValueModel->setFieldId($childrenFieldId);
                    $childrenCustomFieldValueModel->setValues(
                        (new NumericCustomFieldValueCollection())
                            ->add((new NumericCustomFieldValueModel())->setValue($book['UF_CHIDLREN']))
                    );
                    $leadCustomFieldsValues->add($childrenCustomFieldValueModel);
                }
            }

            $totalPeople = (int) ($book['UF_ADULTS'] ?? 0)
                + (int) ($book['UF_CHIDLREN'] ?? ($book['UF_CHILDREN'] ?? 0))
                + (int) ($book['UF_PENSIONER'] ?? ($book['UF_PENSIONER'] ?? 0))
                + (int) ($book['UF_INVALID'] ?? ($book['UF_INVALID'] ?? 0))
                + (int) ($book['UF_STUDENT'] ?? ($book['UF_STUDENT'] ?? 0))
                + (int) ($book['UF_BENEFICIARIES'] ?? ($book['UF_BENEFICIARIES'] ?? 0));
            if ($totalPeople > 0) {
                $totalPeopleFieldId = (int) Option::get('TOTAL_PEOPLE_FIELD_ID');
                if ($totalPeopleFieldId > 0) {
                    $totalPeopleCustomFieldValueModel = new TextCustomFieldValuesModel();
                    $totalPeopleCustomFieldValueModel->setFieldId($totalPeopleFieldId);
                    $totalPeopleCustomFieldValueModel->setValues(
                        (new TextCustomFieldValueCollection())
                            ->add((new TextCustomFieldValueModel())->setValue((string) $totalPeople))
                    );
                    $leadCustomFieldsValues->add($totalPeopleCustomFieldValueModel);
                }
            }

            $seatsValue = $book['UF_SEATS'] ?? '';
            if (is_array($seatsValue)) {
                $seatsValue = array_filter(array_map('trim', $seatsValue), 'strlen');
                $seats = implode(', ', $seatsValue);
            } else {
                $seats = trim((string) $seatsValue);
            }
            if ($seats !== '') {
                $busSeatFieldId = (int) Option::get('BUS_SEAT_FIELD_ID');
                if ($busSeatFieldId > 0) {
                    $busSeatCustomFieldValueModel = new TextCustomFieldValuesModel();
                    $busSeatCustomFieldValueModel->setFieldId($busSeatFieldId);
                    $busSeatCustomFieldValueModel->setValues(
                        (new TextCustomFieldValueCollection())
                            ->add((new TextCustomFieldValueModel())->setValue($seats))
                    );
                    $leadCustomFieldsValues->add($busSeatCustomFieldValueModel);
                }
            }

            $placementName = '';
            if (!empty($book['UF_PLACEMENT'])) {
                $placement = Placements::getById((int) $book['UF_PLACEMENT'], ['ID', 'NAME']);
                $placementName = trim((string) ($placement['NAME'] ?? ''));
            }
            $roomName = '';
            if (!empty($book['UF_ROOM'])) {
                $roomName = trim((string) Rooms::nameById((int) $book['UF_ROOM']));
            }
            if ($placementName !== '' && $roomName !== '') {
                $accommodationTypeFieldId = (int) Option::get('ACCOMMODATION_TYPE_FIELD_ID');
                if ($accommodationTypeFieldId > 0) {
                    $accommodationTypeCustomFieldValueModel = new TextCustomFieldValuesModel();
                    $accommodationTypeCustomFieldValueModel->setFieldId($accommodationTypeFieldId);
                    $accommodationTypeCustomFieldValueModel->setValues(
                        (new TextCustomFieldValueCollection())
                            ->add((new TextCustomFieldValueModel())->setValue($placementName . ':' . $roomName))
                    );
                    $leadCustomFieldsValues->add($accommodationTypeCustomFieldValueModel);
                }
            }

            $orderNumberFieldId = (int) Option::get('ORDER_NUMBER_FIELD_ID');
            if ($orderNumberFieldId > 0) {
                $orderNumberCustomFieldValueModel = new TextCustomFieldValuesModel();
                $orderNumberCustomFieldValueModel->setFieldId($orderNumberFieldId);
                $orderNumberCustomFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue((string) $arOrder['UF_XML_ID']))
                );
                $leadCustomFieldsValues->add($orderNumberCustomFieldValueModel);
            }

            $clientPhoneRaw = trim((string) ($client['PERSONAL_PHONE'] ?? ''));
            $clientPhone = self::normalizePhone($clientPhoneRaw);
            if ($clientPhone !== '') {
                $phoneFieldId = (int) Option::get('PHONE_FIELD_ID');
                if ($phoneFieldId > 0) {
                    $phoneCustomFieldValueModel = new TextCustomFieldValuesModel();
                    $phoneCustomFieldValueModel->setFieldId($phoneFieldId);
                    $phoneCustomFieldValueModel->setValues(
                        (new TextCustomFieldValueCollection())
                            ->add((new TextCustomFieldValueModel())->setValue($clientPhone))
                    );
                    $leadCustomFieldsValues->add($phoneCustomFieldValueModel);
                }
            }
            if ($client['UF_CID']) {
                $cidFieldId = (int) Option::get('CID_FIELD_ID');
                if ($cidFieldId > 0) {
                    $cidCustomFieldValueModel = new TextCustomFieldValuesModel();
                    $cidCustomFieldValueModel->setFieldId($cidFieldId);
                    $cidCustomFieldValueModel->setValues(
                        (new TextCustomFieldValueCollection())
                            ->add((new TextCustomFieldValueModel())->setValue($client['UF_CID']))
                    );
                    $leadCustomFieldsValues->add($cidCustomFieldValueModel);
                }
            }

            $userTypeFieldId = (int) Option::get('USER_TYPE_FIELD_ID');
            if ($userTypeFieldId > 0) {
                $userTypeValue = $isAgent ? 'агент' : 'турист';
                $userTypeCustomFieldValueModel = new TextCustomFieldValuesModel();
                $userTypeCustomFieldValueModel->setFieldId($userTypeFieldId);
                $userTypeCustomFieldValueModel->setValues(
                    (new TextCustomFieldValueCollection())
                        ->add((new TextCustomFieldValueModel())->setValue($userTypeValue))
                );
                $leadCustomFieldsValues->add($userTypeCustomFieldValueModel);
            }

            $lead->setCustomFieldsValues($leadCustomFieldsValues);

            // Создаём сделку без привязки контакта, чтобы избежать контроля дублей на стороне CRM
            $leadsService = $apiClient->leads();
            try {
                $lead = $leadsService->addOne($lead);

                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_leads_logs/leads_' . date('d_m_y_H_i_s') . '.txt'))
                    ->write('Lead ID: ' . $lead->getId() . ' | Order ID: ' . $arOrder['ID']);

                // Поиск контакта по телефону/email и привязка к сделке (избегаем контроля дублей CRM)
                $clientName = trim((string) ($client['FULL_NAME'] ?? ''));
                $clientEmail = trim((string) ($client['EMAIL'] ?? ''));
                if ($clientPhone !== '' || $clientEmail !== '') {
                    self::findOrCreateContactAndLinkToLead($apiClient, $lead, $clientPhone, $clientName, $clientEmail);
                }

                $existingLead = tables\LeadsTable::getList([
                    'filter' => [
                        'LEAD_ID' => $lead->getId()
                    ],
                    'select' => ['ID'],
                ])->fetch();

                if (!$existingLead) {
                    tables\LeadsTable::add([
                        'LEAD_ID' => $lead->getId(),
                        'ORDER_ID' => $arOrder['ID']
                    ]);
                }
            } catch (AmoCRMApiErrorResponseException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                    ->write($e->getMessage() . ': ' . json_encode($e->getValidationErrors()));
            }
        }
    }

    /**
     * Создаёт сделку и контакт в amoCRM по элементу инфоблока заявок.
     *
     * @param int $elementId
     * @return void
     */
    public static function createLeadAndContactFromIblockElement($elementId): void
    {
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
        $phoneRaw = trim((string) ($element['PROPERTY_USER_PHONE_VALUE'] ?? ''));
        $phone = self::normalizePhone($phoneRaw);
        $currentPage = trim((string) ($element['PROPERTY_CURRENT_PAGE_VALUE'] ?? ''));

        $apiClient = self::initApiClient();

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

        try {
            // Создаём сделку без привязки контакта, чтобы избежать контроля дублей на стороне CRM
            $lead = $apiClient->leads()->addOne($lead);
            (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_leads_logs/leads_' . date('d_m_y_H_i_s') . '.txt'))
                ->write('Lead ID: ' . $lead->getId() . ' | Element ID: ' . $elementId);

            // Поиск контакта по телефону/email и привязка к сделке (избегаем контроля дублей CRM)
            if ($phone !== '' || $email !== '') {
                self::findOrCreateContactAndLinkToLead($apiClient, $lead, $phone, $userName, $email);
            }
        } catch (AmoCRMApiException $e) {
            (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_errors_logs/amointegration_' . date('d_m_y_H_i_s') . '.txt'))
                ->write($e->getMessage());
        }
    }
}
