<?php

namespace App\DataFixtures;

use App\Repository\EmplacementRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\Emplacement;

class ImportEmplacementFixtures extends Fixture
{
    private $encoder;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    public function __construct(EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->emplacementRepository = $emplacementRepository;
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

        $i = 1;
        foreach($rows as $row) {
            dump($i);
            $i++;

            $label = $row[0];
            $emplacement = $this->emplacementRepository->findOneBy(['label' => $label]);

            if (empty($emplacement)) {
                $emplacement = new Emplacement();
                $emplacement
                    ->setLabel($row[0])
                    ->setDescription($row[1]);
                $manager->persist($emplacement);
            }
        }
        $manager->flush();
    }
}
