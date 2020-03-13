<?php


namespace App\EventListener;


use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

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

    /**
     * @param ReferenceArticle $referenceArticle
     * @throws \Exception
     */
    public function postUpdate(ReferenceArticle $referenceArticle)
    {
        $this->refArticleService->treatAlert($referenceArticle);
        $referenceArticle
            ->setQuantiteDisponible(($referenceArticle->getQuantiteStock() ?? 0) - ($referenceArticle->getQuantiteReservee() ?? 0));
        $this->entityManager->flush();
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @throws \Exception
     */
    public function postPersist(ReferenceArticle $referenceArticle)
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->refArticleService->treatAlert($referenceArticle);
        }
        $referenceArticle
            ->setQuantiteDisponible(($referenceArticle->getQuantiteStock() ?? 0) - ($referenceArticle->getQuantiteReservee() ?? 0));
        $this->entityManager->flush();
    }
}
