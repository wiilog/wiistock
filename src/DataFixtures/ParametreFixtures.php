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
				'label' => 'ajout quantité',
				'type' => 'list',
				'elements' => ['sur article', 'sur référence'],
				'default' => 'sur article'
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
