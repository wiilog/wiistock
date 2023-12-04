<?php

namespace App\EventListener;

use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Contracts\Service\Attribute\Required;

#[Deprecated]
class RefArticleQuantityNotifier {

    #[Required]
    public RefArticleDataService $refArticleService;

    #[Required]
    public EntityManagerInterface $entityManager;

    public static bool $disableReferenceUpdate = false;

    #[Deprecated]
    public function postUpdate(ReferenceArticle $referenceArticle): void
    {
        $this->handleReference($referenceArticle);
    }

    #[Deprecated]
    public function postPersist(ReferenceArticle $referenceArticle): void
    {
        $this->handleReference($referenceArticle);
    }

    #[Deprecated]
    private function handleReference(ReferenceArticle $referenceArticle): void
    {
        if (!self::$disableReferenceUpdate && $this->entityManager->isOpen()) {
            $this->refArticleService->treatAlert($this->entityManager, $referenceArticle);
            $available = ($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
            $referenceArticle->setQuantiteDisponible($available);
            $this->entityManager->flush();
        }
    }

}
