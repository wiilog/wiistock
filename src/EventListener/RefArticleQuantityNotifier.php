<?php


namespace App\EventListener;


use App\Entity\ReferenceArticle;
use App\Service\FileUploader;
use App\Service\RefArticleDataService;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class RefArticleQuantityNotifier
{

    private $refArticleService;

    public function __construct(RefArticleDataService $refArticleDataService)
    {
        $this->refArticleService = $refArticleDataService;
    }

    public function preUpdate(ReferenceArticle $referenceArticle, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('quantiteStock') && $referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $preQuantity = $event->getOldValue('quantiteStock');
            $postQuantity = $event->getNewValue('quantiteStock');
            $this->refArticleService->treatAlert($referenceArticle, $postQuantity);
        }
    }

    public function prePersist(ReferenceArticle $referenceArticle, LifecycleEventArgs $event)
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->refArticleService->treatAlert($referenceArticle, $referenceArticle->getQuantiteStock());
        }
    }
}
