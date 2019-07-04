<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class PatchRefArticles extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var ArticleRepository
	 */
	private $articleRepository;


	public function __construct(ArticleRepository $articleRepository)
	{
		$this->articleRepository = $articleRepository;
	}

	public function load(ObjectManager $manager)
	{
		// patch spécifique pour dédoublonner les références des articles
		$doublons = $this->articleRepository->findDoublons();
		$i = 0;
		dump(count($doublons) . ' références en doublon.');

		foreach ($doublons as $doublon) { /** @var Article $doublon */
			$refArray = explode('-', $doublon->getReference());
			$newRef = $refArray[0] . '-' . $i;
			$doublon->setReference($newRef);
			$i++;
			dump($i);
		}

		$manager->flush();
	}

	public static function getGroups():array {
		return ['refarticles'];
	}

}
