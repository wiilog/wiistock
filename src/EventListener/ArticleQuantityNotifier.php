<?php

namespace App\EventListener;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Entity\Utilisateur;
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

    private static $referenceArticlesToUpdate = [];
    private static $referenceArticlesUpdating = false;

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
     * @throws ORMException
     */
    public function postFlush() {
        if (!self::$referenceArticlesUpdating) {
            self::$referenceArticlesUpdating = true;

            $cleanedEntityManager = $this->getEntityManager();

            foreach (self::$referenceArticlesToUpdate as $item) {
                $referenceArticle = $item['referenceArticle'];

                $this->refArticleService->updateRefArticleQuantities($cleanedEntityManager, $referenceArticle);

                $this->refArticleService->treatAlert($cleanedEntityManager, $referenceArticle);
                $articles = $item['articles'];
                foreach ($articles as $articleId => $article) {
                    $this->treatAlert($cleanedEntityManager, $article);
                }
                $cleanedEntityManager->flush();
            }

            self::$referenceArticlesToUpdate = [];
            self::$referenceArticlesUpdating = false;
        }
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postUpdate(Article $article) {
        $this->saveArticle($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postPersist(Article $article) {
        $this->saveArticle($article);
    }

    /**
     * @param Article $article
     * @throws Exception
     */
    public function postRemove(Article $article) {
        $this->saveArticle($article);
    }

    /**
     * @param Article $article
     * @throws ORMException
     */
    private function saveArticle(Article $article) {
        $cleanedEntityManager = $this->getEntityManager(true);
        $cleanedEntityManager->clear();

        $articleFournisseur = $article->getArticleFournisseur();
        if(isset($articleFournisseur)) {
            $referenceArticle = $articleFournisseur->getReferenceArticle();
            $referenceArticleId = $referenceArticle->getId();
            if (!isset(self::$referenceArticlesToUpdate[$referenceArticleId])) {
                self::$referenceArticlesToUpdate[$referenceArticleId] = [
                    'referenceArticle' => $referenceArticle,
                    'articles' => []
                ];
            }
            $articleId = $article->getId();
            self::$referenceArticlesToUpdate[$referenceArticleId]['articles'][$articleId] = $article;
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

    private function treatAlert(EntityManagerInterface $entityManager,
                                Article $article)
    {
        if ($article->getExpiryDate()) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));
            $expires = clone $now;
            $expires->modify("{$this->expiryDelay}day");

            $existing = $entityManager->getRepository(Alert::class)->findForArticle($article, Alert::EXPIRY);

            //more than one expiry alert is an invalid state, so remove them to reset
            if (count($existing) > 1) {
                foreach ($existing as $alert) {
                    $entityManager->remove($alert);
                }

                $existing = null;
            }

            if ($expires >= $article->getExpiryDate() && !$existing) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($now);

                $entityManager->persist($alert);

                if ($article->getStatut()->getCode() !== Article::STATUT_INACTIF) {
                    $managers = $article->getArticleFournisseur()
                        ->getReferenceArticle()
                        ->getManagers()
                        ->toArray();
                    $this->alertService->sendExpiryMails($managers, $article, $this->expiryDelay);
                }
            } else if ($now < $article->getExpiryDate() && $existing) {
                $entityManager->remove($existing[0]);
            }
        }
    }
}
