<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\ParametrageGlobal;
use App\Entity\Type;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Persistence\ObjectManager;


class PatchCollinsFixtures extends Fixture implements FixtureGroupInterface
{
	private $specificService;

	public function __construct(SpecificService $specificService)
	{
		$this->specificService = $specificService;
	}

	public function load(ObjectManager $manager)
    {
    	$isCollins = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_COLLINS);

    	if ($isCollins) {
			// spécifique collins champ libre 'BL' (numéro BL d'un article, à afficher sur étiquette)

			$typeRepository = $manager->getRepository(Type::class);
			$champLibreRepository = $manager->getRepository(ChampLibre::class);
			$categorieCLRepository = $manager->getRepository(CategorieCL::class);
			$parametrageGlobalRepository = $manager->getRepository(ParametrageGlobal::class);
            $champLibreWanted = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CL_USED_IN_LABELS);
            $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, Type::LABEL_STANDARD);
			$categorieCL = $categorieCLRepository->findOneBy(['label' => CategorieCL::ARTICLE]);

			$cl = $champLibreRepository->findOneByCategoryCLAndLabel(CategorieCL::ARTICLE, $champLibreWanted);
			if (empty($cl)) {
				$cl = new ChampLibre();
				$cl
					->setLabel($champLibreWanted)
					->setType($type)
					->setCategorieCL($categorieCL)
					->setTypage(ChampLibre::TYPE_TEXT);
				$manager->persist($cl);
				$manager->flush();

				dump('création du champ libre ' . $champLibreWanted);
			}
		}
    }

    public static function getGroups():array {
        return ['collins', 'fixtures'];
    }

}
