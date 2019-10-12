<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class PatchRefArticles extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var ArticleRepository
	 */
	private $articleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;


	public function __construct(ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository)
	{
		$this->articleRepository = $articleRepository;
		$this->referenceArticleRepository = $referenceArticleRepository;
	}

	public function load(ObjectManager $manager)
	{
		// patch spécifique pour dédoublonner les références des articles
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $formattedDate = $date->format('ym');
		$doublons = $this->articleRepository->findDoublons();
		$counter = 1;

		$saveLastRefToRestart = null;
		foreach ($doublons as $doublon) {
			$refArray = explode('-', $doublon->getReference());
			$reference = $refArray[0];

			if ($reference != $saveLastRefToRestart) {
			    $counter = 1;
            }
            $saveLastRefToRestart = $reference;

            $formattedCounter = sprintf('%05u', $counter);
            $referenceRef = $doublon->getArticleFournisseur()->getReferenceArticle()->getReference();
			$newRef = $referenceRef . $formattedDate . $formattedCounter;
			$doublon->setReference($newRef);
			$counter++;
		}

		$manager->flush();
	}

	public static function getGroups():array {
		return ['refarticles'];
	}

}
