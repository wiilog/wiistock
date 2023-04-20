<?php


namespace App\DataFixtures;

use App\Entity\FieldsParam;

use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class ChampsFixesFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $listEntityFieldCodes = [
            FieldsParam::ENTITY_CODE_RECEPTION => [
                ['code' => FieldsParam::FIELD_CODE_FOURNISSEUR, 'label' => FieldsParam::FIELD_LABEL_FOURNISSEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_NUM_COMMANDE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_ATTENDUE, 'label' => FieldsParam::FIELD_LABEL_DATE_ATTENDUE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_DATE_COMMANDE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_UTILISATEUR, 'label' => FieldsParam::FIELD_LABEL_UTILISATEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_TRANSPORTEUR, 'label' => FieldsParam::FIELD_LABEL_TRANSPORTEUR, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMPLACEMENT, 'label' => FieldsParam::FIELD_LABEL_EMPLACEMENT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_ANOMALIE, 'label' => FieldsParam::FIELD_LABEL_ANOMALIE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_STORAGE_LOCATION, 'label' => FieldsParam::FIELD_LABEL_STORAGE_LOCATION, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_REC, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_REC, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ATTACHMENTS, 'label' => FieldsParam::FIELD_LABEL_ATTACHMENTS, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
            ],

            FieldsParam::ENTITY_CODE_DEMANDE => [
                ['code' => FieldsParam::FIELD_CODE_TYPE_DEMANDE, 'label' => FieldsParam::FIELD_LABEL_TYPE_DEMANDE, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'modalType' => FieldsParam::MODAL_TYPE],
                ['code' => FieldsParam::FIELD_CODE_RECEIVER_DEMANDE, 'label' => FieldsParam::FIELD_LABEL_RECEIVER_DEMANDE, 'values' => [], 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'default' => false, 'modalType' => FieldsParam::MODAL_RECEIVER],
                ['code' => FieldsParam::FIELD_CODE_EXPECTED_AT, 'label' => FieldsParam::FIELD_LABEL_EXPECTED_AT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_DEMANDE_PROJECT, 'label' => FieldsParam::FIELD_LABEL_DEMANDE_PROJECT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],

            FieldsParam::ENTITY_CODE_ARRIVAGE => [
                ['code' => FieldsParam::FIELD_CODE_ARRIVAL_TYPE, 'label' => FieldsParam::FIELD_LABEL_ARRIVAL_TYPE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_BUYERS_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CHAUFFEUR_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CARRIER_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PJ_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PJ_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PROVIDER_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_TARGET_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_TARGET_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PRINT_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PRINT_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUM_BL_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_CUSTOMS_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CUSTOMS_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_FROZEN_ARRIVAGE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_PROJECT_NUMBER, 'label' => FieldsParam::FIELD_LABEL_PROJECT_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FieldsParam::FIELD_CODE_BUSINESS_UNIT, 'label' => FieldsParam::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false, 'modalType' => FieldsParam::MODAL_TYPE_FREE],
                ['code' => FieldsParam::FIELD_CODE_DROP_LOCATION_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_DROP_LOCATION_ARRIVAGE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_PROJECT, 'label' => FieldsParam::FIELD_LABEL_PROJECT, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],

            FieldsParam::ENTITY_CODE_DISPATCH => [
                ['code' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_CARRIER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_CARRIER_TRACKING_NUMBER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_RECEIVER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_RECEIVER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DEADLINE_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_DEADLINE_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMAILS, 'label' => FieldsParam::FIELD_LABEL_EMAILS_DISPATCH, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'modalType' => FieldsParam::MODAL_TYPE_FREE],
                ['code' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_COMMAND_NUMBER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENT_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_COMMENT_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_ATTACHMENTS_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_ATTACHMENTS_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_BUSINESS_UNIT, 'label' => FieldsParam::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'modalType' => FieldsParam::MODAL_TYPE_FREE],
                ['code' => FieldsParam::FIELD_CODE_PROJECT_NUMBER, 'label' => FieldsParam::FIELD_LABEL_PROJECT_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_LOCATION_PICK, 'label' => FieldsParam::FIELD_LABEL_LOCATION_PICK, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_LOCATION_DROP, 'label' => FieldsParam::FIELD_LABEL_LOCATION_DROP, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_DESTINATION, 'label' => FieldsParam::FIELD_LABEL_DESTINATION, 'displayedCreate' => false,'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_REQUESTER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_REQUESTER_DISPATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
            ],

            FieldsParam::ENTITY_CODE_HANDLING => [
                ['code' => FieldsParam::FIELD_CODE_LOADING_ZONE, 'label' => FieldsParam::FIELD_LABEL_LOADING_ZONE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_UNLOADING_ZONE, 'label' => FieldsParam::FIELD_LABEL_UNLOADING_ZONE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'modalType' => FieldsParam::MODAL_TYPE_FREE],
                ['code' => FieldsParam::FIELD_CODE_CARRIED_OUT_OPERATION_COUNT, 'label' => FieldsParam::FIELD_LABEL_CARRIED_OUT_OPERATION_COUNT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_RECEIVERS_HANDLING, 'label' => FieldsParam::FIELD_LABEL_RECEIVERS_HANDLING, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => true, 'modalType' => FieldsParam::MODAL_TYPE_USER]
            ],

            FieldsParam::ENTITY_CODE_TRUCK_ARRIVAL => [
                ['code' => FieldsParam::FIELD_CODE_TRUCK_ARRIVAL_UNLOADING_LOCATION, 'label' => FieldsParam::FIELD_LABEL_TRUCK_ARRIVAL_UNLOADING_LOCATION, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FieldsParam::FIELD_CODE_TRUCK_ARRIVAL_REGISTRATION_NUMBER, 'label' => FieldsParam::FIELD_LABEL_TRUCK_ARRIVAL_REGISTRATION_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FieldsParam::FIELD_CODE_TRUCK_ARRIVAL_DRIVER, 'label' => FieldsParam::FIELD_LABEL_TRUCK_ARRIVAL_DRIVER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FieldsParam::FIELD_CODE_TRUCK_ARRIVAL_CARRIER, 'label' => FieldsParam::FIELD_LABEL_TRUCK_ARRIVAL_CARRIER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
            ],

            FieldsParam::ENTITY_CODE_ARTICLE => [
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_UNIT_PRICE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_UNIT_PRICE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_BATCH, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_BATCH, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_ANOMALY, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_ANOMALY, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_EXPIRY_DATE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_EXPIRY_DATE, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_COMMENT, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_COMMENT, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_DELIVERY_NOTE_LINE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_DELIVERY_NOTE_LINE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_MANUFACTURE_DATE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_MANUFACTURE_DATE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_PRODUCTION_DATE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_PRODUCTION_DATE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_PURCHASE_ORDER_LINE, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_PURCHASE_ORDER_LINE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_ARTICLE_NATIVE_COUNTRY, 'label' => FieldsParam::FIELD_LABEL_ARTICLE_NATIVE_COUNTRY, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false],
            ],

            FieldsParam::ENTITY_CODE_EMERGENCY => [
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_BUYER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_BUYER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_PROVIDER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_PROVIDER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_COMMAND_NUMBER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_COMMAND_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_POST_NUMBER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_POST_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_CARRIER_TRACKING_NUMBER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_CARRIER_TRACKING_NUMBER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_CARRIER, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_CARRIER, 'displayedCreate' => true, 'displayedEdit' => true, 'displayedFilters' => false, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_TYPE, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_TYPE, 'displayedCreate' => false, 'displayedEdit' => false, 'displayedFilters' => false, 'modalType' => FieldsParam::MODAL_TYPE_FREE, 'values' => []],
            ]
        ];

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
        $existingFields = $fieldsParamRepository->findAll();

        $mappedExistingFields = Stream::from($existingFields)
            ->keymap(function($field) {
                return [$field->getEntityCode() . '-' . $field->getFieldCode(), $field];
            })
            ->toArray();

        foreach($listEntityFieldCodes as $fieldEntity => $listFieldCodes) {
            foreach($listFieldCodes as $fieldCode) {

                $fieldUniqueKey = $fieldEntity . '-' . $fieldCode['code'];
                $field = $mappedExistingFields[$fieldUniqueKey] ?? null;

                  if (isset($field))  {
                      unset($mappedExistingFields[$fieldUniqueKey]);
                  };

                if(!$field) {
                    $field = new FieldsParam();
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
                    ->setFieldRequiredHidden($fieldCode['hidden'] ?? false);

                if (isset($fieldCode['modalType'])) {
                    $field->setModalType($fieldCode['modalType']);
                }
                $manager->flush();
            }
        }

        /** @var FieldsParam $field */
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
