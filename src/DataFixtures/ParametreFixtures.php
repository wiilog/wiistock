<?php

namespace App\DataFixtures;

use App\Entity\Parametre;
use App\Repository\ParametreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class ParametreFixtures extends Fixture implements FixtureGroupInterface
{

	/**
	 * @var ParametreRepository
	 */
	private $parametreRepository;

    public function __construct(ParametreRepository $parametreRepository)
    {
    	$this->parametreRepository = $parametreRepository;
    }

    public function load(ObjectManager $manager)
    {
		$parameters = [
			[
				'label' => Parametre::LABEL_AJOUT_QUANTITE,
				'type' => Parametre::TYPE_LIST,
				'elements' => [Parametre::VALUE_PAR_ART, Parametre::VALUE_PAR_REF],
				'default' => Parametre::VALUE_PAR_REF
			],
		];

		foreach ($parameters as $parameter) {
			$param = $this->parametreRepository->findBy(['label' => $parameter['label']]);

			if (empty($param)) {
				$param = new Parametre();
				$param
					->setLabel($parameter['label'])
					->setTypage($parameter['type'])
					->setDefaultValue($parameter['default'])
					->setElements($parameter['elements']);
				$manager->persist($param);
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['param', 'fixtures'];
    }
}
