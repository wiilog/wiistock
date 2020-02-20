<?php

namespace App\DataFixtures;

use App\Entity\InventoryMission;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class PatchMissionInvFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

	/**
	 * @var EmplacementRepository
	 */
    private $emplacementRepository;


    public function __construct(EmplacementRepository $emplacementRepository, ReferenceArticleRepository $referenceArticleRepository)
    {
    	$this->referenceArticleRepository = $referenceArticleRepository;
    	$this->emplacementRepository = $emplacementRepository;
    }

    public function load(ObjectManager $manager)
    {
    	// création mission inventaire
    	$startDate = new \DateTime('now');
    	$endDate = new \DateTime('2019-12-31');

    	$mission = new InventoryMission();
    	$mission
			->setStartPrevDate($startDate)
			->setEndPrevDate($endDate);
    	$manager->persist($mission);
    	$manager->flush();


    	// ajout références à la mission
		$path ="src/DataFixtures/Csv/inventaire.csv";
		$file = fopen($path, "r");

		$rows = [];
		while (($data = fgetcsv($file, 1000, ";")) !== false) {
			$rows[] = $data;
		}

		array_shift($rows); // supprime la 1è ligne d'en-têtes

		foreach ($rows as $row) {
			$ref = $this->referenceArticleRepository->findOneByReference($row[0]);

			if ($ref) {
				$mission->addRefArticle($ref);
			} else {
				dump('la réf ' . $row[0] . ' n\'existe pas');
			}


		}

		$manager->flush();
		fclose($file);
	}

    public static function getGroups():array {
        return ['inventory'];
    }
}
