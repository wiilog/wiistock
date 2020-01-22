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
    public function postUpdate(Article $article)
    {
        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $referenceArticle->treatAlert();
        $this->entityManager->flush();
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postPersist(Article $article)
    {
        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $referenceArticle->treatAlert();
        $this->entityManager->flush();
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postRemove(Article $article)
    {
        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $referenceArticle->treatAlert();
        $this->entityManager->flush();
    }
}
