<?php

namespace App\EventListener;

use App\Entity\Article;
use App\Entity\Setting;
use App\Service\AlertService;
use App\Service\RefArticleDataService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[Deprecated]
class ArticleQuantityNotifier {

    #[Required]
    public RefArticleDataService $refArticleService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public AlertService $alertService;

    #[Required]
    public SettingsService $settingsService;

    private static array $referenceArticlesToUpdate = [];

    public static bool $referenceArticlesUpdating = false;

    public static bool $disableArticleUpdate = false;

    #[Deprecated]
    public function postFlush(): void {
        if (!empty(self::$referenceArticlesToUpdate) && !self::$disableArticleUpdate && !self::$referenceArticlesUpdating) {
            self::$referenceArticlesUpdating = true;

            $cleanedEntityManager = $this->getEntityManager();
            $expiryDelay = $this->settingsService->getValue($cleanedEntityManager, Setting::STOCK_EXPIRATION_DELAY, 0);

            $this->refArticleService->updateRefArticleQuantities(
                $cleanedEntityManager,
                Stream::from(self::$referenceArticlesToUpdate)
                    ->map(fn($item) => $item['referenceArticle'])
                    ->toArray()
            );

            foreach (self::$referenceArticlesToUpdate as $item) {
                $referenceArticle = $item['referenceArticle'];


                $this->refArticleService->treatAlert($cleanedEntityManager, $referenceArticle);
                $articles = $item['articles'];
                foreach ($articles as $article) {
                    $this->alertService->treatArticleAlert($cleanedEntityManager, $article, $expiryDelay);
                }
                $cleanedEntityManager->flush();
            }

            self::$referenceArticlesToUpdate = [];
            self::$referenceArticlesUpdating = false;
        }
    }

    #[Deprecated]
    public function postUpdate(Article $article) {
        $this->saveArticle($article);
    }

    #[Deprecated]
    public function postPersist(Article $article) {
        $this->saveArticle($article);
    }

    #[Deprecated]
    public function postRemove(Article $article) {
        $this->saveArticle($article);
    }

    #[Deprecated]
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

    #[Deprecated]
    private function getEntityManager(bool $cleaned = false): EntityManagerInterface {
        return $this->entityManager->isOpen() && !$cleaned
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
