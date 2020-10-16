<?php


namespace App\EventListener;


use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Service\RefArticleDataService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Exception;

class ArticleQuantityNotifier
{

    private $refArticleService;
    private $entityManager;
    private $expiryDelay;

    public function __construct(RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager) {
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;

        $this->expiryDelay = $entityManager->getRepository(ParametrageGlobal::class)
            ->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY) ?: "0h";
        $this->expiryDelay = str_replace("s", "week", $this->expiryDelay);
        $this->expiryDelay = str_replace("j", "day", $this->expiryDelay);
        $this->expiryDelay = str_replace("h", "hour", $this->expiryDelay);
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
            $this->treatAlert($article);
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

    private function treatAlert(Article $article) {
        $now = new DateTime("now", new DateTimeZone("Europe/Paris"));
        $alertDay = clone $article->getExpiryDate();
        $alertDay->modify($this->expiryDelay);

        $existing = $this->entityManager->getRepository(Alert::class)->findForReference($article, Alert::EXPIRY);

        if($now >= $alertDay && !$existing) {
            $alert = new Alert();
            $alert->setArticle($article);
            $alert->setType(Alert::EXPIRY);
            $alert->setDate($now);

            $this->entityManager->persist($alert);
        } else if($now < $alertDay && $existing) {
            $this->entityManager->remove($existing);
        }
    }
}
