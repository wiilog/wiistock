<?php

namespace App\EventListener;

use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleQuantityNotifier {

    private $refArticleService;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, RefArticleDataService $refArticleDataService) {
        $this->entityManager = $entityManager;
        $this->refArticleService = $refArticleDataService;
    }

    public function postUpdate(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    public function postPersist(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    private function handleReference(ReferenceArticle $referenceArticle) {
        if ($this->entityManager->isOpen()) {
            $this->refArticleService->treatAlert($this->entityManager, $referenceArticle);
            $available = ($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
            $referenceArticle->setQuantiteDisponible($available);
            // WIIS-6777 - Quantities override by notifier
            $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock());
            $this->entityManager->flush();
        }
    }

}
