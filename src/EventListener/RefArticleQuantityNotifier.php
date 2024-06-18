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

    private static array $untreatedReferences = [];

    #[Deprecated]
    public function preUpdate(ReferenceArticle $referenceArticle): void {
        $this->handleReference($referenceArticle);
    }

    #[Deprecated]
    public function prePersist(ReferenceArticle $referenceArticle): void {
        $this->handleReference($referenceArticle);
    }


    #[Deprecated]
    public function postFlush(): void {
        if (!self::$disableReferenceUpdate
            && $this->entityManager->isOpen()
            && !empty(self::$untreatedReferences)) {
            foreach (self::$untreatedReferences as $referenceArticle) {
                $this->refArticleService->treatAlert($this->entityManager, $referenceArticle);
            }
            self::$untreatedReferences = [];
            $this->entityManager->flush();
        }
    }

    #[Deprecated]
    private function handleReference(ReferenceArticle $referenceArticle): void
    {
        if (!self::$disableReferenceUpdate && $this->entityManager->isOpen()) {
            $barCode = $referenceArticle->getBarCode();
            $available = ($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
            $referenceArticle->setQuantiteDisponible($available);
            self::$untreatedReferences[$barCode] = $referenceArticle;
        }
    }

}
