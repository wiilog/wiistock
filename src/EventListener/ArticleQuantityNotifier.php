<?php


namespace App\EventListener;


use App\Entity\Article;
use App\Service\RefArticleDataService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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
        $this->treatAlertAndUpdateRefArticleQuantities($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postPersist(Article $article) {
        $this->treatAlertAndUpdateRefArticleQuantities($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postRemove(Article $article) {
        $this->treatAlertAndUpdateRefArticleQuantities($article);
    }

    /**
     * @param Article $article
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function treatAlertAndUpdateRefArticleQuantities(Article $article) {
        $articleFournisseur = $article->getArticleFournisseur();
        if (isset($articleFournisseur)) {
            $referenceArticle = $articleFournisseur->getReferenceArticle();
			$this->refArticleService->treatAlert($referenceArticle);
			$this->refArticleService->recalculateAndUpdateStockQuantityForRef($referenceArticle);
			$referenceArticle
                ->setQuantiteDisponible(($referenceArticle->getQuantiteStock() ?? 0) - ($referenceArticle->getQuantiteReservee() ?? 0));
            $this->entityManager->flush();
        }
    }
}
