<?php


namespace App\DataFixtures;

use App\Entity\FieldsParam;

use App\Repository\FieldsParamRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class ChampsFixesReceptionFixtures extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var FieldsParamRepository
	 */
	private $fieldsParamRepository;

	public function __construct(FieldsParamRepository $fieldsParamRepository)
	{
		$this->fieldsParamRepository = $fieldsParamRepository;
	}

	public function load(ObjectManager $manager)
    {
    	$listFieldCodes = [
    		[FieldsParam::FIELD_CODE_FOURNISSEUR, FieldsParam::FIELD_LABEL_FOURNISSEUR],
			[FieldsParam::FIELD_CODE_NUM_COMMANDE, FieldsParam::FIELD_LABEL_NUM_COMMANDE],
			[FieldsParam::FIELD_CODE_COMMENTAIRE, FieldsParam::FIELD_LABEL_COMMENTAIRE],
			[FieldsParam::FIELD_CODE_DATE_ATTENDUE, FieldsParam::FIELD_LABEL_DATE_ATTENDUE],
			[FieldsParam::FIELD_CODE_DATE_COMMANDE, FieldsParam::FIELD_LABEL_DATE_COMMANDE],
			[FieldsParam::FIELD_CODE_UTILISATEUR, FieldsParam::FIELD_LABEL_UTILISATEUR],
			[FieldsParam::FIELD_CODE_NUM_RECEPTION, FieldsParam::FIELD_LABEL_NUM_RECEPTION],
			[FieldsParam::FIELD_CODE_TRANSPORTEUR, FieldsParam::FIELD_LABEL_TRANSPORTEUR],

            [FieldsParam::FIELD_CODE_BUYERS_ARRIVAGE, FieldsParam::FIELD_LABEL_BUYERS_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_CHAUFFEUR_ARRIVAGE, FieldsParam::FIELD_LABEL_CHAUFFEUR_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_COMMENTAIRE_ARRIVAGE, FieldsParam::FIELD_LABEL_COMMENTAIRE_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_CARRIER_ARRIVAGE, FieldsParam::FIELD_LABEL_CARRIER_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_STATUT_ARRIVAGE, FieldsParam::FIELD_LABEL_STATUT_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_PJ_ARRIVAGE, FieldsParam::FIELD_LABEL_PJ_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_PROVIDER_ARRIVAGE, FieldsParam::FIELD_LABEL_PROVIDER_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_TARGET_ARRIVAGE, FieldsParam::FIELD_LABEL_TARGET_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_PRINT_ARRIVAGE, FieldsParam::FIELD_LABEL_PRINT_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_NUM_BL_ARRIVAGE, FieldsParam::FIELD_LABEL_NUM_BL_ARRIVAGE, true],
            [FieldsParam::FIELD_CODE_NUMERO_TRACKING_ARRIVAGE, FieldsParam::FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE, true],
        ];

    	foreach ($listFieldCodes as $fieldCode) {
			$field = $this->fieldsParamRepository->findOneBy(
			    [
			        'fieldCode' => $fieldCode[0],
                    'entityCode' => isset($fieldCode[2]) ? FieldsParam::ENTITY_CODE_ARRIVAGE : FieldsParam::ENTITY_CODE_RECEPTION
                ]);
			if (!$field) {
				$field = new FieldsParam();
				$field
					->setEntityCode(isset($fieldCode[2]) ? FieldsParam::ENTITY_CODE_ARRIVAGE : FieldsParam::ENTITY_CODE_RECEPTION)
					->setFieldLabel($fieldCode[1])
                    ->setDisplayed(true)
					->setFieldCode($fieldCode[0]);
				$manager->persist($field);
				$manager->flush();
				dump('Champ fixe ' . isset($fieldCode[2]) ? FieldsParam::ENTITY_CODE_ARRIVAGE : FieldsParam::ENTITY_CODE_RECEPTION . ' / ' . $fieldCode[0] . ' créé.');
			} else {
			    $field->setDisplayed(true);
            }
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['setFields', 'fixtures'];
    }
}
