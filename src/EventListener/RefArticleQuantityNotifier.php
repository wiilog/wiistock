<?php


namespace App\EventListener;


use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;

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
        $entityManager = $this->getEntityManager();
        $this->refArticleService->treatAlert($referenceArticle);
        $referenceArticle
            ->setQuantiteDisponible(($referenceArticle->getQuantiteStock() ?? 0) - ($referenceArticle->getQuantiteReservee() ?? 0));
        $entityManager->flush();
    }

    /**
     * @param ReferenceArticle $referenceArticle
     * @throws \Exception
     */
    public function postPersist(ReferenceArticle $referenceArticle)
    {
        $entityManager = $this->getEntityManager();

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->refArticleService->treatAlert($referenceArticle);
        }

        $referenceArticle
            ->setQuantiteDisponible(($referenceArticle->getQuantiteStock() ?? 0) - ($referenceArticle->getQuantiteReservee() ?? 0));

        $entityManager->flush();
    }

    /**
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : EntityManager::Create($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
