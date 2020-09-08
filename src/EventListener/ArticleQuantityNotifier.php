<?php


namespace App\EventListener;


use App\Entity\Article;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;

class ArticleQuantityNotifier
{

    private $refArticleService;
    private $entityManager;

    public function __construct(RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager) {
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postUpdate(Article $article) {
        $entityManager = $this->getEntityManager();
        $this->treatAlertAndUpdateRefArticleQuantities($entityManager, $article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postPersist(Article $article) {
        $entityManager = $this->getEntityManager();
        $this->treatAlertAndUpdateRefArticleQuantities($entityManager, $article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postRemove(Article $article) {
        $entityManager = $this->getEntityManager(true);
        $this->treatAlertAndUpdateRefArticleQuantities($entityManager, $article);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Article $article
     * @throws Exception
     */
    private function treatAlertAndUpdateRefArticleQuantities(EntityManagerInterface $entityManager, Article $article) {
        $articleFournisseur = $article->getArticleFournisseur();
        if (isset($articleFournisseur)) {
            $referenceArticle = $articleFournisseur->getReferenceArticle();
            $this->refArticleService->updateRefArticleQuantities($referenceArticle);
            $entityManager->flush();
            $this->refArticleService->treatAlert($referenceArticle);
            $entityManager->flush();
        }
    }

    /**
     * @param bool $cleaned
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(bool $cleaned = false): EntityManagerInterface {
        return $this->entityManager->isOpen() && !$cleaned
            ? $this->entityManager
            : EntityManager::Create($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
