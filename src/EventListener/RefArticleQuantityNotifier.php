<?php

namespace App\EventListener;

use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Contracts\Service\Attribute\Required;

#[Deprecated]
class RefArticleQuantityNotifier {

    #[Required]
    public RefArticleDataService $refArticleService;

    public static bool $disableReferenceUpdate = false;

    private static array $untreatedReferences = [];

    #[Deprecated]
    public function preUpdate(ReferenceArticle $referenceArticle,
                              PreUpdateEventArgs $args): void {
        $this->handleReference(
            $args->getObjectManager(),
            $referenceArticle
        );
    }

    #[Deprecated]
    public function prePersist(ReferenceArticle $referenceArticle,
                               PrePersistEventArgs $args): void {
        $this->handleReference(
            $args->getObjectManager(),
            $referenceArticle
        );
    }


    #[Deprecated]
    public function postFlush(PostFlushEventArgs $args): void {
        $entityManager = $args->getObjectManager();
        if (!self::$disableReferenceUpdate
            && $entityManager->isOpen()
            && !empty(self::$untreatedReferences)) {
            foreach (self::$untreatedReferences as $referenceArticle) {
                $this->refArticleService->treatAlert($entityManager, $referenceArticle);
            }
            self::$untreatedReferences = [];
            $entityManager->flush();
        }
    }

    #[Deprecated]
    private function handleReference(EntityManagerInterface $entityManager,
                                     ReferenceArticle $referenceArticle): void
    {
        if (!self::$disableReferenceUpdate && $entityManager->isOpen()) {
            $barCode = $referenceArticle->getBarCode();
            $available = ($referenceArticle->getQuantiteStock() - $referenceArticle->getQuantiteReservee());
            $referenceArticle->setQuantiteDisponible($available);
            self::$untreatedReferences[$barCode] = $referenceArticle;
        }
    }

}
