<?php


namespace App\DataFixtures;

use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\Type\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use WiiCommon\Helper\Stream;

class FixedFieldFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager): void {
        $output = new ConsoleOutput();
        $typeRepository = $manager->getRepository(Type::class);

        $listEntityFieldCodes = [
            FixedFieldStandard::ENTITY_CODE_RECEPTION => [
                ['code' => FixedFieldStandard::FIELD_CODE_FOURNISSEUR, 'label' => FixedFieldStandard::FIELD_LABEL_FOURNISSEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_NUM_COMMANDE, 'label' => FixedFieldStandard::FIELD_LABEL_NUM_COMMANDE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_COMMENTAIRE, 'label' => FixedFieldStandard::FIELD_LABEL_COMMENTAIRE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_DATE_ATTENDUE, 'label' => FixedFieldStandard::FIELD_LABEL_DATE_ATTENDUE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_DATE_COMMANDE, 'label' => FixedFieldStandard::FIELD_LABEL_DATE_COMMANDE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_UTILISATEUR, 'label' => FixedFieldStandard::FIELD_LABEL_UTILISATEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_TRANSPORTEUR, 'label' => FixedFieldStandard::FIELD_LABEL_TRANSPORTEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_EMPLACEMENT, 'label' => FixedFieldStandard::FIELD_LABEL_EMPLACEMENT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_ANOMALIE, 'label' => FixedFieldStandard::FIELD_LABEL_ANOMALIE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_STORAGE_LOCATION, 'label' => FixedFieldStandard::FIELD_LABEL_STORAGE_LOCATION, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_EMERGENCY_REC, 'label' => FixedFieldStandard::FIELD_LABEL_EMERGENCY_REC, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ATTACHMENTS, 'label' => FixedFieldStandard::FIELD_LABEL_ATTACHMENTS, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
            ],

            FixedFieldStandard::ENTITY_CODE_DEMANDE => [
                ['code' => FixedFieldStandard::FIELD_CODE_TYPE_DEMANDE, 'label' => FixedFieldStandard::FIELD_LABEL_TYPE_DEMANDE, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE],
                ['code' => FixedFieldStandard::FIELD_CODE_RECEIVER_DEMANDE, 'label' => FixedFieldStandard::FIELD_LABEL_RECEIVER_DEMANDE, 'values' => [], 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false, 'elementsType' => FixedFieldStandard::ELEMENTS_RECEIVER],
                ['code' => FixedFieldStandard::FIELD_CODE_EXPECTED_AT, 'label' => FixedFieldStandard::FIELD_LABEL_EXPECTED_AT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE, 'label' => FixedFieldStandard::FIELD_LABEL_DESTINATION_DEMANDE,  'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_LOCATION_BY_TYPE],
                ['code' => FixedFieldStandard::FIELD_CODE_DELIVERY_REQUEST_PROJECT, 'label' => FixedFieldStandard::FIELD_LABEL_DELIVERY_REQUEST_PROJECT . '<img src="/svg/information.svg" width="12px" height="12px" class="has-tooltip ml-1" title="Va chercher dans le référentiel projet">', 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],

            FixedFieldStandard::ENTITY_CODE_ARRIVAGE => [
                ['code' => FixedFieldStandard::FIELD_CODE_ARRIVAL_TYPE, 'label' => FixedFieldStandard::FIELD_LABEL_ARRIVAL_TYPE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_BUYERS_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_BUYERS_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_CHAUFFEUR_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_CHAUFFEUR_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_COMMENTAIRE_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_CARRIER_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_CARRIER_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_PJ_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_PJ_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_PROVIDER_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_PROVIDER_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_RECEIVERS, 'label' => FixedFieldStandard::FIELD_LABEL_RECEIVERS, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_PRINT_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_PRINT_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_NUM_COMMANDE_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_NUM_BL_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_CUSTOMS_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_CUSTOMS_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_FROZEN_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_FROZEN_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER, 'label' => FixedFieldStandard::FIELD_LABEL_PROJECT_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT, 'label' => FixedFieldStandard::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE],
                ['code' => FixedFieldStandard::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'label' => FixedFieldStandard::FIELD_LABEL_DROP_LOCATION_ARRIVAGE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_PROJECT, 'label' => FixedFieldStandard::FIELD_LABEL_PROJECT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],

            FixedFieldStandard::ENTITY_CODE_HANDLING => [
                ['code' => FixedFieldStandard::FIELD_CODE_LOADING_ZONE, 'label' => FixedFieldStandard::FIELD_LABEL_LOADING_ZONE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_UNLOADING_ZONE, 'label' => FixedFieldStandard::FIELD_LABEL_UNLOADING_ZONE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_EMERGENCY, 'label' => FixedFieldStandard::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE],
                ['code' => FixedFieldStandard::FIELD_CODE_CARRIED_OUT_OPERATION_COUNT, 'label' => FixedFieldStandard::FIELD_LABEL_CARRIED_OUT_OPERATION_COUNT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_RECEIVERS_HANDLING, 'label' => FixedFieldStandard::FIELD_LABEL_RECEIVERS_HANDLING, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_USER],
                ['code' => FixedFieldEnum::object->name, 'label' => FixedFieldEnum::object->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true]
            ],

            FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL => [
                ['code' => FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_UNLOADING_LOCATION, 'label' => FixedFieldStandard::FIELD_LABEL_TRUCK_ARRIVAL_UNLOADING_LOCATION, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_REGISTRATION_NUMBER, 'label' => FixedFieldStandard::FIELD_LABEL_TRUCK_ARRIVAL_REGISTRATION_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_DRIVER, 'label' => FixedFieldStandard::FIELD_LABEL_TRUCK_ARRIVAL_DRIVER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER, 'label' => FixedFieldStandard::FIELD_LABEL_TRUCK_ARRIVAL_CARRIER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldEnum::carrierTrackingNumber->name, 'label' => FixedFieldEnum::carrierTrackingNumber->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
            ],

            FixedFieldStandard::ENTITY_CODE_ARTICLE => [
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_UNIT_PRICE, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_UNIT_PRICE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_BATCH, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_BATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_ANOMALY, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_ANOMALY, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_EXPIRY_DATE, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_EXPIRY_DATE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_COMMENT, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_COMMENT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_DELIVERY_NOTE_LINE, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_DELIVERY_NOTE_LINE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_MANUFACTURED_AT, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_MANUFACTURED_AT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_PRODUCTION_DATE, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_PRODUCTION_DATE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_PURCHASE_ORDER_LINE, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_PURCHASE_ORDER_LINE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_ARTICLE_NATIVE_COUNTRY, 'label' => FixedFieldStandard::FIELD_LABEL_ARTICLE_NATIVE_COUNTRY, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],
        ];

        $subLinesFieldCodes = [
            SubLineFixedField::ENTITY_CODE_DEMANDE_REF_ARTICLE => [
                ['code' => SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT, 'label' => SubLineFixedField::FIELD_LABEL_DEMANDE_REF_ARTICLE_PROJECT, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' => SubLineFixedField::DISPLAY_CONDITION_REFERENCE_TYPE, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT, 'label' => SubLineFixedField::FIELD_LABEL_DEMANDE_REF_ARTICLE_COMMENT, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DEMANDE_REF_ARTICLE_NOTES, 'label' => SubLineFixedField::FIELD_LABEL_DEMANDE_REF_ARTICLE_NOTES, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' =>  null, 'conditionFixedFieldValue' => [], 'required' => false],
            ],
            SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT => [
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LENGTH, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE_NUMBER],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WIDTH, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE_NUMBER],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_HEIGHT, 'displayed' => false, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE_NUMBER],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WEIGHT, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_VOLUME, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_COMMENT, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_ACTION_DATE, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LAST_ACTION_DATE, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_OPERATOR, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
                ['code' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_STATUS, 'label' => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_STATUS, 'displayed' => true, 'displayedUnderCondition' => false, 'conditionFixedField' => null, 'conditionFixedFieldValue' => [], 'required' => false],
            ],
        ];

        $fixedFieldByType = [
            FixedFieldStandard::ENTITY_CODE_DISPATCH => [
                ['code' => FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CARRIER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CARRIER_TRACKING_NUMBER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_RECEIVER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_DEADLINE_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_DEADLINE_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_EMAILS, 'label' => FixedFieldStandard::FIELD_LABEL_EMAILS_DISPATCH, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_EMERGENCY, 'label' => FixedFieldStandard::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE],
                ['code' => FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_COMMAND_NUMBER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_COMMENT_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_ATTACHMENTS_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_ATTACHMENTS_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT, 'label' => FixedFieldStandard::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE],
                ['code' => FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER, 'label' => FixedFieldStandard::FIELD_LABEL_PROJECT_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_LOCATION_PICK, 'label' => FixedFieldStandard::FIELD_LABEL_LOCATION_PICK, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_LOCATION_DROP, 'label' => FixedFieldStandard::FIELD_LABEL_LOCATION_DROP, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_DESTINATION, 'label' => FixedFieldStandard::FIELD_LABEL_DESTINATION, 'displayedCreate' => false,'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_REQUESTER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FixedFieldStandard::FIELD_CODE_CUSTOMER_NAME_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CUSTOMER_NAME . '<img src="/svg/information.svg" width="12px" height="12px" class="has-tooltip ml-1" title="Donnée provenant du référentiel client">', 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_CUSTOMER_PHONE_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CUSTOMER_PHONE . '<img src="/svg/information.svg" width="12px" height="12px" class="has-tooltip ml-1" title="Donnée provenant du référentiel client">', 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CUSTOMER_RECIPIENT_DISPATCH . '<img src="/svg/information.svg" width="12px" height="12px" class="has-tooltip ml-1" title="Donnée provenant du référentiel client">', 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_CUSTOMER_ADDRESS . '<img src="/svg/information.svg" width="12px" height="12px" class="has-tooltip ml-1" title="Donnée provenant du référentiel client">', 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false],
                ['code' => FixedFieldStandard::FIELD_CODE_TYPE_DISPATCH, 'label' => FixedFieldStandard::FIELD_LABEL_DISPATCH_TYPE , 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
            ],
            FixedFieldStandard::ENTITY_CODE_PRODUCTION => [
                ['code' => FixedFieldEnum::manufacturingOrderNumber->name, 'label' => FixedFieldEnum::manufacturingOrderNumber->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldEnum::emergency->name, 'label' => FixedFieldEnum::emergency->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_TYPE_FREE, 'values' => []],
                ['code' => FixedFieldEnum::expectedAt->name, 'label' => FixedFieldEnum::expectedAt->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'elementsType' => FixedFieldStandard::ELEMENTS_EXPECTED_AT_BY_TYPE, 'values' => [], 'elements' => []],
                ['code' => FixedFieldEnum::projectNumber->name, 'label' => FixedFieldEnum::projectNumber->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldEnum::productArticleCode->name, 'label' => FixedFieldEnum::productArticleCode->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldEnum::dropLocation->name, 'label' => FixedFieldEnum::dropLocation->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldEnum::comment->name, 'label' => FixedFieldEnum::comment->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldEnum::attachments->name, 'label' => FixedFieldEnum::attachments->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FixedFieldEnum::quantity->name, 'label' => FixedFieldEnum::quantity->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldEnum::lineCount->name, 'label' => FixedFieldEnum::lineCount->value, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FixedFieldEnum::destinationLocation->name, 'label' => FixedFieldEnum::destinationLocation->value, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],
            FixedFieldStandard::ENTITY_CODE_STOCK_EMERGENCY => [
                ['code' => FixedFieldEnum::comment->name, 'label' => FixedFieldEnum::comment->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::orderNumber->name, 'label' => FixedFieldEnum::orderNumber->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::carrierTrackingNumber->name, 'label' => FixedFieldEnum::carrierTrackingNumber->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::carrier->name, 'label' => FixedFieldEnum::carrier->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::attachments->name, 'label' => FixedFieldEnum::attachments->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::expectedEmergencyLocation->name, 'label' => FixedFieldEnum::expectedEmergencyLocation->value, 'displayedCreate' => true, 'displayedEdit' => true],
            ],
            FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY => [
                ['code' => FixedFieldEnum::buyer->name, 'label' => FixedFieldEnum::buyer->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::supplier->name, 'label' => FixedFieldEnum::supplier->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::orderNumber->name, 'label' => FixedFieldEnum::orderNumber->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::postNumber->name, 'label' => FixedFieldEnum::postNumber->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::carrierTrackingNumber->name, 'label' => FixedFieldEnum::carrierTrackingNumber->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::carrier->name, 'label' => FixedFieldEnum::carrier->value, 'displayedCreate' => true, 'displayedEdit' => true],
                ['code' => FixedFieldEnum::internalArticleCode->name, 'label' => FixedFieldEnum::internalArticleCode->value, 'displayedCreate' => false, 'displayedEdit' => false],
                ['code' => FixedFieldEnum::supplierArticleCode->name, 'label' => FixedFieldEnum::supplierArticleCode->value, 'displayedCreate' => false, 'displayedEdit' => false],
            ],
        ];

        $fieldsParamStandardRepository = $manager->getRepository(FixedFieldStandard::class);
        $fieldsParamByTypeRepository = $manager->getRepository(FixedFieldByType::class);
        $subLineFieldsParamRepository = $manager->getRepository(SubLineFixedField::class);
        $existingFieldsStandard = $fieldsParamStandardRepository->findAll();
        $existingFieldsByTypes = $fieldsParamByTypeRepository->findAll();
        $existingSubLinesFields = $subLineFieldsParamRepository->findAll();


        $mappedExistingFields = Stream::from($existingFieldsStandard, $existingFieldsByTypes, $existingSubLinesFields)
            ->keymap(function(FixedField $field) {
                return [$field->getEntityCode() . '-' . $field->getFieldCode(), $field];
            })
            ->toArray();

        foreach($subLinesFieldCodes as $fieldEntity => $listFieldCodes) {
            foreach ($listFieldCodes as $fieldCode) {
                $fieldUniqueKey = $fieldEntity . '-' . $fieldCode['code'];
                $field = $mappedExistingFields[$fieldUniqueKey] ?? null;

                if (isset($field))  {
                    unset($mappedExistingFields[$fieldUniqueKey]);
                }

                if(!$field) {
                    $field = new SubLineFixedField();
                    $field
                        ->setEntityCode($fieldEntity)
                        ->setFieldCode($fieldCode['code'])
                        ->setDisplayed($fieldCode['displayed'])
                        ->setDisplayedUnderCondition($fieldCode['displayedUnderCondition'])
                        ->setConditionFixedField($fieldCode['conditionFixedField'])
                        ->setConditionFixedFieldValue($fieldCode['conditionFixedFieldValue'])
                        ->setRequired($fieldCode['required'])
                        ->setElements($fieldCode['values'] ?? null)
                        ->setElementsType($fieldCode['elementsType'] ?? null);

                    $manager->persist($field);
                    $output->writeln('Champ fixe de ligne ' . $fieldEntity . ' / ' . $fieldCode['code'] . ' créé.');
                }
                $field->setFieldLabel($fieldCode['label']);

                $manager->flush();
            }
        }

        foreach($listEntityFieldCodes as $fieldEntity => $listFieldCodes) {
            foreach($listFieldCodes as $fieldCode) {

                $fieldUniqueKey = $fieldEntity . '-' . $fieldCode['code'];
                $field = $mappedExistingFields[$fieldUniqueKey] ?? null;

                  if (isset($field))  {
                      unset($mappedExistingFields[$fieldUniqueKey]);
                  }

                if(!$field) {
                    $field = new FixedFieldStandard();
                    $field
                        ->setEntityCode($fieldEntity)
                        ->setFieldCode($fieldCode['code'])
                        ->setDisplayedCreate($fieldCode['displayedCreate'])
                        ->setDisplayedEdit($fieldCode['displayedEdit'])
                        ->setDisplayedFilters($fieldCode['displayedFilters'])
                        ->setRequiredEdit($fieldCode['default'] ?? false)
                        ->setRequiredCreate($fieldCode['default'] ?? false)
                        ->setElements($fieldCode['values'] ?? null);

                    $manager->persist($field);
                    $output->writeln('Champ fixe ' . $fieldEntity . ' / ' . $fieldCode['code'] . ' créé.');
                }
                $field
                    ->setFieldLabel($fieldCode['label'])
                    ->setFieldRequiredHidden($fieldCode['hidden'] ?? null)
                    ->setElementsType($fieldCode['elementsType'] ?? null);

                $manager->flush();
            }
        }

        foreach($fixedFieldByType as $fieldEntity => $listFieldCodes) {
            $entityTypes = $typeRepository->findByCategoryLabels([$fieldEntity]);
            $entityTypes = new ArrayCollection($entityTypes);
            $emptyCollection = new ArrayCollection();

            foreach($listFieldCodes as $fieldCode) {
                $fieldUniqueKey = $fieldEntity . '-' . $fieldCode['code'];
                $field = $mappedExistingFields[$fieldUniqueKey] ?? null;

                if (isset($field))  {
                    unset($mappedExistingFields[$fieldUniqueKey]);
                }

                if(!$field) {
                    $field = new FixedFieldByType();
                    $field
                        ->setEntityCode($fieldEntity)
                        ->setFieldCode($fieldCode['code'])
                        ->setElements($fieldCode['values'] ?? null)
                        ->setDisplayedCreate(($fieldCode['displayedCreate'] ?? false) ? $entityTypes : $emptyCollection)
                        ->setDisplayedEdit(($fieldCode['displayedEdit'] ?? false) ? $entityTypes : $emptyCollection)
                        ->setRequiredEdit(($fieldCode['default'] ?? false) ? $entityTypes : $emptyCollection)
                        ->setRequiredCreate(($fieldCode['default'] ?? false) ? $entityTypes : $emptyCollection);

                    $manager->persist($field);
                    $output->writeln('Champ fixe ' . $fieldEntity . ' / ' . $fieldCode['code'] . ' créé.');
                }
                $field
                    ->setFieldLabel($fieldCode['label'])
                    ->setElementsType($fieldCode['elementsType'] ?? null);

                $manager->flush();
            }
        }

        /** @var FixedField $field */
        foreach ($mappedExistingFields as $field) {
            $manager->remove($field);
            $output->writeln('Champ fixe ' . $field->getEntityCode() . ' / ' . $field->getFieldCode() . ' supprimé.');
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['setFields', 'fixtures'];
    }

}
