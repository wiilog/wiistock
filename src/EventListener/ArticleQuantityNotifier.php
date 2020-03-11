<?php


namespace App\EventListener;


use App\Entity\Article;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class ArticleQuantityNotifier
{

    private $refArticleService;
    private $entityManager;

    public function __construct(RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager)
    {
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postUpdate(Article $article) {
        $this->treatAlert($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postPersist(Article $article) {
        $this->treatAlert($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postRemove(Article $article) {
        $this->treatAlert($article);
    }

    private function treatAlert(Article $article) {
        $articleFournisseur = $article->getArticleFournisseur();
        if (isset($articleFournisseur)) {
            $referenceArticle = $articleFournisseur->getReferenceArticle();
			$this->refArticleService->treatAlert($referenceArticle);
            $this->entityManager->flush();
        }
    }
}
