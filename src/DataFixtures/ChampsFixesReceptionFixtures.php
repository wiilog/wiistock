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
			];

    	foreach ($listFieldCodes as $fieldCode) {
			$field = $this->fieldsParamRepository->findBy(['fieldCode' => $fieldCode[0]]);
			if (!$field) {
				$field = new FieldsParam();
				$field
					->setEntityCode(FieldsParam::ENTITY_CODE_RECEPTION)
					->setFieldLabel($fieldCode[1])
					->setFieldCode($fieldCode[0]);
				$manager->persist($field);
				$manager->flush();
				dump('Champ fixe ' . FieldsParam::ENTITY_CODE_RECEPTION . ' / ' . $fieldCode[0] . ' créé.');
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['setFields', 'fixtures'];
    }
}
