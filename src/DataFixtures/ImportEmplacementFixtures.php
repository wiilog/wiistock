<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Emplacement;

class ImportEmplacementFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

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

        $i = 1;
        foreach($rows as $row) {
            dump($i);
            $i++;

            $label = $row[0];

            $description = isset($row[1]) ? $row[1] : $label;
            $emplacement = $emplacementRepository->findOneBy(['label' => $label]);

            if (empty($emplacement)) {
                $emplacement = new Emplacement();
                $emplacement
                    ->setLabel($label)
                    ->setIsActive(true)
                    ->setDescription($description);
                $manager->persist($emplacement);
            }
        }
        $manager->flush();
    }
}
