<?php

namespace travelsoft\amocrm;

use travelsoft\booking\Logger;
use travelsoft\booking\stores\Vouchers;
use travelsoft\booking\stores\Users;
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\DateCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\DateCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateCustomFieldValueCollection;
use AmoCRM\Models\LeadModel;
class EventsHandlers {

    function onAfterOrderAdd($orderId) {

        \Bitrix\Main\Loader::includeModule('travelsoft.travelbooking');

        $arOrder = Vouchers::getById($orderId);

        if (!empty($arOrder) && $arOrder['ID']) {

            $client = Users::getById($arOrder['UF_CLIENT'], ['*', 'UF_*']);

            $apiClient = new AmoCRMApiClient(Option::get('CLIENT_ID'), Option::get('CLIENT_SECRET'), Option::get('REDIRECT_URL'));

            $baseDomain = Option::get('BASE_DOMAIN') . '.amocrm.ru';

            $apiClient->setAccountBaseDomain($baseDomain);

            Utils::applyAccessToken($apiClient);

            $lead = new LeadModel();

            $book = current($arOrder['BOOKINGS']);

            $lead->setName(trim($client['FULL_NAME'] ?: $client['EMAIL']));
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

            $leadsCollection = new LeadsCollection();
            $leadsCollection->add($lead);
            $leadsService = $apiClient->leads();
            try {
                $lead = $leadsService->addOne($lead);

                tables\LeadsTable::add([
                    'LEAD_ID' => $lead->getId(),
                    'ORDER_ID' => $arOrder['ID']
                ]);
            } catch (AmoCRMApiException $e) {
                (new Logger($_SERVER['DOCUMENT_ROOT'] . '/upload/amocrm_integration_logs/amointegration_' . date('d_m_y') . '.txt'))->write($e->getMessage());
            }
        }
    }

}
