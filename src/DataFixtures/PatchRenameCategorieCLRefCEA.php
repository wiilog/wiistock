<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;

use App\Entity\CategoryType;
use App\Repository\CategorieCLRepository;

use App\Repository\CategoryTypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;


class PatchRenameCategorieCLRefCEA extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var CategorieCLRepository
	 */
	private $categorieCLRepository;

	/**
	 * @var CategoryTypeRepository
	 */
	private $categoryTypeRepository;


	public function __construct(CategoryTypeRepository $categoryTypeRepository, CategorieCLRepository $categorieCLRepository)
	{
		$this->categorieCLRepository = $categorieCLRepository;
		$this->categoryTypeRepository = $categoryTypeRepository;
	}

	public function load(ObjectManager $manager)
	{
		$categorieRefCEA = $this->categorieCLRepository->findOneByLabel('reference CEA');

		if ($categorieRefCEA) {
			$categorieRefCEA->setLabel(CategorieCL::REFERENCE_ARTICLE);
			$manager->flush();
		}

		$categoryTypeArt = $this->categoryTypeRepository->findOneBy(['label' => 'articles et références CEA']);

		if ($categoryTypeArt) {
			$categoryTypeArt->setLabel(CategoryType::ARTICLE);
			$manager->flush();
		}

	}

	public static function getGroups():array {
		return ['patchRefCEA'];
	}

}
