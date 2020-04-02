<?php

namespace App\DataFixtures;

use App\Entity\Transporteur;
use App\Repository\TransporteurRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ImportTransporteurFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    public function __construct(TransporteurRepository $transporteurRepository)
    {
        $this->transporteurRepository = $transporteurRepository;
    }

    public function load(ObjectManager $manager)
    {
		$path = "src/DataFixtures/transporteurs.csv";
		$file = fopen($path, "r");

        $firstRow = true;

        while (($data = fgetcsv($file, 1000, ";")) !== false) {
        	if ($firstRow) {
        		$firstRow = false;
			} else {
				$row = array_map('utf8_encode', $data);
				$code = $row[1] ?? $row[0];
				$transporteur = $this->transporteurRepository->findOneByCode($code);

				if (empty($transporteur)) {
                    $transporteur = new Transporteur();
					$transporteur
						->setLabel($row[0])
						->setCode($code);
					$manager->persist($transporteur);
        			$manager->flush();
				}
			}
        }
    }

	public static function getGroups(): array
	{
		return ['import-transporteurs'];
	}
}
