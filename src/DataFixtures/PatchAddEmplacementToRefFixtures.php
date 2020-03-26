<?php


namespace App\DataFixtures;

use App\Entity\Emplacement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

use App\Repository\ReferenceArticleRepository;
use App\Repository\EmplacementRepository;

class PatchAddEmplacementToRefFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var ReferenceArticleRepository
     */
     private $referenceArticleRepository;

    public function __construct(ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
    }

    public function load(ObjectManager $manager)
    {
        $this->updateRef($manager);
    }

    public function updateRef(ObjectManager $manager)
    {
        $path ="src/DataFixtures/Csv/emplacements.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 10000, ";")) !== false) {
            $rows[] = $data;
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $emplacementRepository = $manager->getRepository(Emplacement::class);

        foreach($rows as $row) {
            $ref = $this->referenceArticleRepository->findOneByReference($row[0]);
            $emplacement = $emplacementRepository->findOneByLabel($row[2]);

            if ($ref) {
            	if ($emplacement) {
					$ref->setEmplacement($emplacement);
					$manager->flush();
				} else {
            		dump('emplacement non trouvé : ' . $row[2]);
				}
            } else {
            	dump('référence non trouvée : ' . $row[0]);
			}
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['emplacement'];
    }
}
