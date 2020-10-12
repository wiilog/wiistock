<?php


namespace App\DataFixtures;

use App\Entity\FieldsParam;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ChampsFixesFixtures extends Fixture implements FixtureGroupInterface {

    public function load(ObjectManager $manager) {
        $listEntityFieldCodes = [
            FieldsParam::ENTITY_CODE_RECEPTION => [
                ['code' => FieldsParam::FIELD_CODE_FOURNISSEUR, 'label' => FieldsParam::FIELD_LABEL_FOURNISSEUR, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_NUM_COMMANDE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_ATTENDUE, 'label' => FieldsParam::FIELD_LABEL_DATE_ATTENDUE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_DATE_COMMANDE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_UTILISATEUR, 'label' => FieldsParam::FIELD_LABEL_UTILISATEUR, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_RECEPTION, 'label' => FieldsParam::FIELD_LABEL_NUM_RECEPTION, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_TRANSPORTEUR, 'label' => FieldsParam::FIELD_LABEL_TRANSPORTEUR, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMPLACEMENT, 'label' => FieldsParam::FIELD_LABEL_EMPLACEMENT, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_ANOMALIE, 'label' => FieldsParam::FIELD_LABEL_ANOMALIE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_STORAGE_LOCATION, 'label' => FieldsParam::FIELD_LABEL_STORAGE_LOCATION, 'displayedFormsCreate' => false, 'displayedFormsEdit' => false, 'displayedFilters' => false],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_REC, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_REC, 'displayedFormsCreate' => false, 'displayedFormsEdit' => false, 'displayedFilters' => false],
            ],

            FieldsParam::ENTITY_CODE_ARRIVAGE => [
                ['code' => FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_BUYERS_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CHAUFFEUR_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CARRIER_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PJ_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PJ_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PROVIDER_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_TARGET_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_TARGET_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PRINT_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PRINT_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUM_BL_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DUTY_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_DUTY_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_FROZEN_ARRIVAGE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_PROJECT_NUMBER, 'label' => FieldsParam::FIELD_LABEL_PROJECT_NUMBER, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => false],
                ['code' => FieldsParam::FIELD_CODE_BUSINESS_UNIT, 'label' => FieldsParam::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => false],
            ],

            FieldsParam::ENTITY_CODE_DISPATCH => [
                ['code' => FieldsParam::FIELD_CODE_CARRIER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_CARRIER_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_CARRIER_TRACKING_NUMBER_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_RECEIVER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_RECEIVER_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_DEADLINE_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_DEADLINE_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMAND_NUMBER_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_COMMAND_NUMBER_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENT_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_COMMENT_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_ATTACHMENTS_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_ATTACHMENTS_DISPATCH, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_BUSINESS_UNIT, 'label' => FieldsParam::FIELD_LABEL_BUSINESS_UNIT, 'values' => [], 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_PROJECT_NUMBER, 'label' => FieldsParam::FIELD_LABEL_PROJECT_NUMBER, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_LOCATION_PICK, 'label' => FieldsParam::FIELD_LABEL_LOCATION_PICK, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_LOCATION_DROP, 'label' => FieldsParam::FIELD_LABEL_LOCATION_DROP, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true, 'default' => true],
            ],
                FieldsParam::ENTITY_CODE_HANDLING => [
                ['code' => FieldsParam::FIELD_CODE_LOADING_ZONE, 'label' => FieldsParam::FIELD_LABEL_LOADING_ZONE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_UNLOADING_ZONE, 'label' => FieldsParam::FIELD_LABEL_UNLOADING_ZONE, 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY, 'values' => ['24h'], 'displayedFormsCreate' => true, 'displayedFormsEdit' => true, 'displayedFilters' => true],
            ]
        ];

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

        foreach($listEntityFieldCodes as $fieldEntity => $listFieldCodes) {
            foreach($listFieldCodes as $fieldCode) {
                $field = $fieldsParamRepository->findOneBy([
                    'fieldCode' => $fieldCode['code'],
                    'entityCode' => $fieldEntity
                ]);

                if(!$field) {
                    $field = new FieldsParam();
                    $field
                        ->setEntityCode($fieldEntity)
                        ->setFieldLabel($fieldCode['label'])
                        ->setDisplayedFormsCreate($fieldCode['displayedFormsCreate'])
                        ->setDisplayedFormsEdit($fieldCode['displayedFormsEdit'])
                        ->setDisplayedFilters($fieldCode['displayedFilters'])
                        ->setMustToModify($fieldCode['default'] ?? false)
                        ->setMustToCreate($fieldCode['default'] ?? false)
                        ->setElements($fieldCode['values'] ?? null)
                        ->setFieldRequiredHidden($fieldCode['hidden'] ?? false)
                        ->setFieldCode($fieldCode['code']);
                    $manager->persist($field);
                    $manager->flush();
                    dump('Champ fixe ' . $fieldEntity . ' / ' . $fieldCode['code'] . ' créé.');
                }
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['setFields', 'fixtures'];
    }

}
