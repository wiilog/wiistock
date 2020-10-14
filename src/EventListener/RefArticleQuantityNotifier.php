<?php

namespace App\EventListener;

use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;

class RefArticleQuantityNotifier {

    private $refArticleService;

    public function __construct(RefArticleDataService $refArticleDataService) {
        $this->refArticleService = $refArticleDataService;
    }

    public function postUpdate(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    public function postPersist(ReferenceArticle $referenceArticle) {
        $this->handleReference($referenceArticle);
    }

    private function handleReference(ReferenceArticle $referenceArticle) {
        if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->refArticleService->treatAlert($referenceArticle);
        }

        $available = $referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee();
        $referenceArticle->setQuantiteDisponible($available);
    }

}
