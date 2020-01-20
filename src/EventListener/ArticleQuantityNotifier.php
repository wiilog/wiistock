<?php


namespace App\EventListener;


use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Service\FileUploader;
use App\Service\RefArticleDataService;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class ArticleQuantityNotifier
{

    private $refArticleService;

    public function __construct(RefArticleDataService $refArticleDataService)
    {
        $this->refArticleService = $refArticleDataService;
    }

    public function preUpdate(Article $article, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('quantite')) {
            $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
            $this->refArticleService->treatAlert($referenceArticle, $referenceArticle->getCalculedAvailableQuantity());
        }
    }

    public function prePersist(Article $article, LifecycleEventArgs $event)
    {
        $referenceArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $this->refArticleService->treatAlert(
            $referenceArticle,
            $referenceArticle->getCalculedAvailableQuantity()
        );
    }
}
