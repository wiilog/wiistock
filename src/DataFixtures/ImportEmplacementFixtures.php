<?php

namespace App\DataFixtures;

use App\Service\EmplacementDataService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Emplacement;

class ImportEmplacementFixtures extends Fixture
{
    public EmplacementDataService $emplacementDataService;

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/emplacements.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $emplacementRepository = $manager->getRepository(Emplacement::class);
        $emplacements = [];
        $i = 1;
        foreach($rows as $row) {
            $i++;

            $label = $row[0];

            $description = isset($row[1]) ? $row[1] : $label;
            $emplacement = $emplacementRepository->findOneBy(['label' => $label]);

            if (empty($emplacement) && !isset($emplacements[$label])) {
                $emplacement = $this->emplacementDataService->persistLocation([
                    "label" => $label,
                    "description" => $description,
                ], $manager);

                $emplacements[$label] = true;
            }
        }
        $manager->flush();
    }
}
