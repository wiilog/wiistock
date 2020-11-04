<?php

namespace App\EventListener;

use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleQuantityNotifier {

    private $manager;
    private $refArticleService;
    private $entityManager;

    public function __construct(EntityManagerInterface $manager, RefArticleDataService $refArticleDataService) {
        $this->manager = $manager;
        $this->refArticleService = $refArticleDataService;
    }

    public function postUpdate(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    public function postPersist(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    private function handleReference(ReferenceArticle $referenceArticle) {
        if ($this->manager->isOpen()) {
            $this->refArticleService->treatAlert($referenceArticle);
            $this->manager->flush();

            $available = $referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee();
            $referenceArticle->setQuantiteDisponible($available);
            $this->manager->flush();
        }
    }

}
