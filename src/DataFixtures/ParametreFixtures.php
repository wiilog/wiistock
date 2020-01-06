<?php

namespace App\DataFixtures;

use App\Entity\ParametrageGlobal;
use App\Entity\Parametre;

use App\Repository\ParametrageGlobalRepository;
use App\Repository\ParametreRepository;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;

class ParametreFixtures extends Fixture implements FixtureGroupInterface
{

	/**
	 * @var ParametreRepository
	 */
	private $parametreRepository;

	/**
	 * @var ParametrageGlobalRepository
	 */
	private $parametreGlobalRepository;

	/**
	 * @var SpecificService
	 */
	private $specificService;

    public function __construct(
    	ParametreRepository $parametreRepository,
		ParametrageGlobalRepository $parametrageGlobalRepository,
		SpecificService $specificService)
    {
    	$this->parametreRepository = $parametreRepository;
    	$this->parametreGlobalRepository = $parametrageGlobalRepository;
    	$this->specificService = $specificService;
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
				dump("création du paramètre " . $parameter['label']);
			}
		}

		$globalParameterLabels = [
			ParametrageGlobal::CREATE_DL_AFTER_RECEPTION,
			ParametrageGlobal::CREATE_PREPA_AFTER_DL,
			ParametrageGlobal::INCLUDE_BL_IN_LABEL
		];

		foreach ($globalParameterLabels as $globalParameterLabel) {
			$globalParam = $this->parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

			if (empty($globalParam)) {
				$isCollins = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS);

				$globalParam = new ParametrageGlobal();
				$globalParam
					->setLabel($globalParameterLabel)
					->setParametre($isCollins);
				$manager->persist($globalParam);
				dump("création du paramètre " . $globalParameterLabel);
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['param', 'fixtures'];
    }
}
