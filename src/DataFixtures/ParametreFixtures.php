<?php

namespace App\DataFixtures;

use App\Entity\Parametre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class ParametreFixtures extends Fixture implements FixtureGroupInterface
{

    public function __construct()
    {
    }

    public function load(ObjectManager $manager)
    {
		$parameters = [
			[
				'label' => Parametre::LABEL_AJOUT_QUANTITE,
				'type' => Parametre::TYPE_LIST,
				'elements' => [Parametre::VALUE_PAR_ART, Parametre::VALUE_PAR_REF],
				'default' => Parametre::VALUE_PAR_ART
			],
		];

		foreach ($parameters as $parameter) {
			$param = new Parametre();
			$param
				->setLabel($parameter['label'])
				->setTypage($parameter['type'])
				->setDefault($parameter['default'])
				->setElements($parameter['elements']);
			$manager->persist($param);
			dump("création du paramètre " . $parameter['label']);
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['param'];
    }
}
