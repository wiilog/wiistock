<?php

namespace App\EventListener;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Service\AlertService;
use App\Service\RefArticleDataService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Exception;

class ArticleQuantityNotifier {

    private $refArticleService;
    private $alertService;
    private $entityManager;
    private $expiryDelay;

    public function __construct(RefArticleDataService $refArticleDataService,
                                AlertService $alertService,
                                EntityManagerInterface $entityManager) {
        $this->refArticleService = $refArticleDataService;
        $this->alertService = $alertService;
        $this->entityManager = $entityManager;

        $this->expiryDelay = $entityManager->getRepository(ParametrageGlobal::class)
            ->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY) ?: 0;
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
        if(isset($articleFournisseur)) {
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
        if($article->getExpiryDate()) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));
            $expires = clone $now;
            $expires->modify("{$this->expiryDelay}day");

            $existing = $this->entityManager->getRepository(Alert::class)->findForArticle($article, Alert::EXPIRY);

            //more than one expiry alert is an invalid state, so remove them to reset
            if(count($existing) > 1) {
                foreach($existing as $alert) {
                    $this->entityManager->remove($alert);
                }

                $existing = null;
            }

            if($now >= $article->getExpiryDate() && !$existing) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($expires);

                $this->entityManager->persist($alert);

                $managers = $article->getArticleFournisseur()
                    ->getReferenceArticle()
                    ->getManagers()
                    ->toArray();

                $this->alertService->sendExpiryMails($managers, $article, $this->expiryDelay);
            } else if($now < $article->getExpiryDate() && $existing) {
                $this->entityManager->remove($existing[0]);
            }
        }
    }

}
