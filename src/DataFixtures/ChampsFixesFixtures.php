<?php


namespace App\DataFixtures;

use App\Entity\FieldsParam;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ChampsFixesFixtures extends Fixture implements FixtureGroupInterface
{

	public function load(ObjectManager $manager)
    {
        $listEntityFieldCodes = [
            FieldsParam::ENTITY_CODE_RECEPTION => [
                ['code' => FieldsParam::FIELD_CODE_FOURNISSEUR, 'label' => FieldsParam::FIELD_LABEL_FOURNISSEUR, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_NUM_COMMANDE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_ATTENDUE, 'label' => FieldsParam::FIELD_LABEL_DATE_ATTENDUE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_DATE_COMMANDE, 'label' => FieldsParam::FIELD_LABEL_DATE_COMMANDE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_UTILISATEUR, 'label' => FieldsParam::FIELD_LABEL_UTILISATEUR, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_RECEPTION, 'label' => FieldsParam::FIELD_LABEL_NUM_RECEPTION, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_TRANSPORTEUR, 'label' => FieldsParam::FIELD_LABEL_TRANSPORTEUR, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_EMPLACEMENT, 'label' => FieldsParam::FIELD_LABEL_EMPLACEMENT, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_ANOMALIE, 'label' => FieldsParam::FIELD_LABEL_ANOMALIE, 'displayed' => true],
            ],

            FieldsParam::ENTITY_CODE_ARRIVAGE => [
                ['code' => FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_BUYERS_ARRIVAGE, 'displayed' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CHAUFFEUR_ARRIVAGE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_COMMENTAIRE_ARRIVAGE, 'displayed' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_CARRIER_ARRIVAGE, 'displayed' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PJ_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PJ_ARRIVAGE, 'displayed' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PROVIDER_ARRIVAGE, 'displayed' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_TARGET_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_TARGET_ARRIVAGE, 'displayed' => true, 'default' => true],
                ['code' => FieldsParam::FIELD_CODE_PRINT_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_PRINT_ARRIVAGE, 'displayed' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_NUM_COMMANDE_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUM_BL_ARRIVAGE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_DUTY_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_DUTY_ARRIVAGE, 'displayed' => true, 'hidden' => true],
                ['code' => FieldsParam::FIELD_CODE_FROZEN_ARRIVAGE, 'label' => FieldsParam::FIELD_LABEL_FROZEN_ARRIVAGE, 'displayed' => true, 'hidden' => true],
            ],

            FieldsParam::ENTITY_CODE_DISPATCH => [
                ['code' => FieldsParam::FIELD_CODE_RECIPIENT_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_RECIPIENT_DISPATCH, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_DEADLINE_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_DEADLINE_DISPATCH, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_EMERGENCY_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_EMERGENCY_DISPATCH, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_COMMENT_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_COMMENT_DISPATCH, 'displayed' => true],
                ['code' => FieldsParam::FIELD_CODE_ATTACHMENTS_DISPATCH, 'label' => FieldsParam::FIELD_LABEL_ATTACHMENTS_DISPATCH, 'displayed' => true],
            ]
        ];

        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);

    	foreach ($listEntityFieldCodes as $fieldEntity => $listFieldCodes) {
            foreach ($listFieldCodes as $fieldCode) {
                $field = $fieldsParamRepository->findOneBy(
                    [
                        'fieldCode' => $fieldCode['code'],
                        'entityCode' => $fieldEntity
                    ]);
                if (!$field) {
                    $field = new FieldsParam();
                    $field
                        ->setEntityCode($fieldEntity)
                        ->setFieldLabel($fieldCode['label'])
                        ->setDisplayed($fieldCode['displayed'])
                        ->setMustToModify($fieldCode['default'] ?? false)
                        ->setMustToCreate($fieldCode['default'] ?? false)
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

    public static function getGroups(): array
    {
        return ['setFields', 'fixtures'];
    }
}
