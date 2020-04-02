<?php

namespace App\DataFixtures;

use App\Entity\Article;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class PatchRefArticles extends Fixture implements FixtureGroupInterface
{

	public function load(ObjectManager $manager)
	{
        $articleRepository = $manager->getRepository(Article::class);

		// patch spécifique pour dédoublonner les références des articles
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $formattedDate = $date->format('ym');
		$doublons = $articleRepository->findDoublons();
		$counter = 0;

		$saveLastRefToRestart = null;
		foreach ($doublons as $doublon) {
			$refArray = explode('-', $doublon->getReference());
			$reference = $refArray[0];

			if ($reference != $saveLastRefToRestart) {
			    $counter = 0;
            }
            $saveLastRefToRestart = $reference;

			do {
				$counter++;
				$formattedCounter = sprintf('%05u', $counter);
				$referenceRef = $doublon->getArticleFournisseur()->getReferenceArticle()->getReference();
				$newRef = $referenceRef . $formattedDate . $formattedCounter;
			} while ($articleRepository->findByReference($newRef));

			$doublon->setReference($newRef);
			$manager->flush();
		}
	}

	public static function getGroups():array {
		return ['doublons-articles'];
	}

}
