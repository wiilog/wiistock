<?php


namespace App\EventListener;


use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class RefArticleQuantityNotifier
{

    private $refArticleService;
    private $entityManager;

    public function __construct(RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager)
    {
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;
    }

    public function preUpdate(ReferenceArticle $referenceArticle, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('quantiteStock') && $referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $referenceArticle->treatAlert();
            $this->entityManager->flush();
        }
    }

    public function postPersist(ReferenceArticle $referenceArticle)
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $referenceArticle->treatAlert();
            $this->entityManager->flush();
        }
    }
}
