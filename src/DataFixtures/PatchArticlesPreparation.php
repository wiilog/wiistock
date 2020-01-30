<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\LigneArticlePreparation;
use App\Entity\Preparation;
use App\Repository\ArticleRepository;
use App\Repository\DemandeRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class PatchArticlesPreparation extends Fixture implements FixtureGroupInterface
{
	/**
	 * @var PreparationRepository
	 */
    private $preparationRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

	public function __construct(PreparationRepository $preparationRepository, StatutRepository $statutRepository)
	{
		$this->preparationRepository = $preparationRepository;
		$this->statutRepository = $statutRepository;
	}

	public function load(ObjectManager $manager)
	{
        $cpt = 0;
        $cptGlobal = 0;
        $preparations = $this->preparationRepository->findAll();
	    foreach ($preparations as $preparation) {
	        $demande = $preparation->getDemande();

	        if ($demande) {
				$articles = $demande->getArticles();
				foreach ($articles as $article) {
					$preparation->addArticle($article);
				}
				$lignesArticles = $demande->getLigneArticle();
				foreach ($lignesArticles as $ligneArticle) {
					$lignesArticlePreparation = new LigneArticlePreparation();
					$lignesArticlePreparation
						->setToSplit($ligneArticle->getToSplit())
						->setQuantitePrelevee($ligneArticle->getQuantitePrelevee())
						->setQuantite($ligneArticle->getQuantite())
						->setReference($ligneArticle->getReference());
					$manager->persist($lignesArticlePreparation);
					$preparation->addLigneArticlePreparation($lignesArticlePreparation);
				}

                $cpt++;
                if ($cpt === 500) {
                    $cptGlobal += $cpt;
                    dump('flush ' . $cptGlobal .' / ' . count($preparations));
                    $manager->flush();
                    $cpt = 0;
                }
			}
        }
        $cptGlobal += $cpt;
        dump('flush ' . $cptGlobal .' / ' . count($preparations));
	    $manager->flush();
	}

	public static function getGroups():array {
		return ['patch-art-prep'];
	}

}
