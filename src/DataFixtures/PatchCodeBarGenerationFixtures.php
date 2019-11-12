<?php


namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;

class PatchCodeBarGenerationFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    private $em;

    public function __construct(ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, EntityManagerInterface $em)
    {
    	$this->articleRepository = $articleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->em = $em;
    }

    public function load(ObjectManager $manager)
    {
		$now = new \DateTime('now');
		$dateCode = $now->format('ym');

    	// création des codes-barres de références
    	$listRef = $this->referenceArticleRepository->findAll();

    	$counterRef = 1;
		foreach ($listRef as $ref) {
			$formattedCounter = sprintf('%08u', $counterRef);
    		$ref->setBarCode(ReferenceArticle::BARCODE_PREFIX . $dateCode . $formattedCounter);
			$counterRef++;
		}
		$manager->flush();

		// création des codes-barres d'articles
		$listeArt = $this->articleRepository->findAll();

		$counterArt = 1;
		foreach ($listeArt as $article) {
			$formattedCounter = sprintf('%08u', $counterArt);
			$article->setBarCode(Article::BARCODE_PREFIX . $dateCode . $formattedCounter);
			$counterArt++;
		}
		$manager->flush();
    }

    public static function getGroups():array {
        return ['barcodes'];
    }
}