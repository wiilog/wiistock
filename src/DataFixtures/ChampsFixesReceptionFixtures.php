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
    		FieldsParam::FIELD_CODE_FOURNISSEUR,
			FieldsParam::FIELD_CODE_NUM_COMMANDE,
			FieldsParam::FIELD_CODE_COMMENTAIRE,
			FieldsParam::FIELD_CODE_DATE_ATTENDUE,
			FieldsParam::FIELD_CODE_DATE_COMMANDE,
			FieldsParam::FIELD_CODE_UTILISATEUR,
			FieldsParam::FIELD_CODE_NUM_RECEPTION,
			];

    	foreach ($listFieldCodes as $fieldCode) {
			$field = $this->fieldsParamRepository->findBy(['fieldCode' => $fieldCode]);
			if (!$field) {
				$field = new FieldsParam();
				$field
					->setEntityCode(FieldsParam::ENTITY_CODE_RECEPTION)
					->setFieldCode($fieldCode);
				$manager->persist($field);
				$manager->flush();
				dump('Champ fixe ' . FieldsParam::ENTITY_CODE_RECEPTION . ' / ' . $fieldCode . ' créé.');
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['setFields', 'fixtures'];
    }
}
