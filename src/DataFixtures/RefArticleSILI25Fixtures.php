<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class RefArticleSILI25Fixtures extends Fixture implements FixtureGroupInterface
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
    	$articles = $this->articleRepository->findByQuantityMoreThan(25);

    	foreach ($articles as $article) { /** @var $article Article */
    		$index = 0;
			while ($article->getQuantite() > 25) {
				$newArticle = new Article();
				$newArticle
					->setArticleFournisseur($article->getArticleFournisseur())
					->setStatut($article->getStatut())
					->setLabel($article->getLabel() . '-' . $index)
					->setQuantite(25)
					->setCommentaire($article->getCommentaire())
					->setConform($article->getConform())
					->setReference($article->getReference())
					->setType($article->getType())
					->setEmplacement($article->getEmplacement());

				$article->setQuantite($article->getQuantite() - 25);
				$index++;
			}
    	}

        $manager->flush();

    }

    public static function getGroups():array {
        return ['SILI25'];
    }

}
