<?php

namespace App\EventListener;

use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Repository\PurchaseRequestLineRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleStateNotifier {

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function postUpdate($entity) {

        $receptionReferenceArticleRepository = $this->entityManager->getRepository(ReceptionReferenceArticle::class);
        $purchaseRequestLineRepository = $this->entityManager->getRepository(PurchaseRequestLine::class);


        if ($entity instanceof Reception) {
            $status = $entity->getStatut() ? $entity->getStatut()->getCode() : null;
            $receptionReferenceArticles = $entity->getReceptionReferenceArticles();

            if ($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
                    $reference = $receptionReferenceArticle->getReferenceArticle();
                    $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                }
            } else {
                foreach ($entity->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                    $reference = $receptionReferenceArticle->getReferenceArticle();
                    $this->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
            }
        } else if ($entity instanceof PurchaseRequest) {
            $status = $entity->getStatus() ? $entity->getStatus()->getState() : null;
            $lines = $entity->getPurchaseRequestLines();

            if ($status === Statut::NOT_TREATED || $status === Statut::IN_PROGRESS) {
                foreach ($lines as $line) {
                    $reference = $line->getReference();
                    $associatedLines = $purchaseRequestLineRepository->findByReferenceArticleAndPurchaseStatus(
                        $reference,
                        [Statut::NOT_TREATED, Statut::IN_PROGRESS]
                    );
                    if (empty($associatedLines)) {
                        $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
                    }
                }
            } else {
                foreach ($lines as $line) {
                    $reference = $line->getReference();
                    $this->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
            }
        }
        $this->entityManager->flush();
    }

    public function postRemove($entity) {

        $receptionReferenceArticleRepository = $this->entityManager->getRepository(ReceptionReferenceArticle::class);
        $purchaseRequestLineRepository = $this->entityManager->getRepository(PurchaseRequestLine::class);


        if ($entity instanceof Reception) {
            $receptionReferenceArticles = $entity->getReceptionReferenceArticles();

            foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
                $reference = $receptionReferenceArticle->getReferenceArticle();
                $this->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
            }
        }
        else if ($entity instanceof PurchaseRequest) {
            $lines = $entity->getPurchaseRequestLines();

            foreach ($lines as $line) {
                $reference = $line->getReference();
                $this->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
            }
        }
        $this->entityManager->flush();
    }

    private function setStateAccordingToRelations(ReferenceArticle $reference,
                                                  PurchaseRequestLineRepository $purchaseRequestLineRepository,
                                                  ReceptionReferenceArticleRepository $receptionReferenceArticleRepository) {
        $associatedLines = $receptionReferenceArticleRepository->findByReferenceArticleAndReceptionStatus(
            $reference,
            [Reception::STATUT_EN_ATTENTE, Reception::STATUT_RECEPTION_PARTIELLE],
        );
        if (!empty($associatedLines)) {
            $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
        } else {
            $associatedLines = $purchaseRequestLineRepository->findByReferenceArticleAndPurchaseStatus(
                $reference,
                [Statut::NOT_TREATED, Statut::IN_PROGRESS]
            );
            if (!empty($associatedLines)) {
                $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
            } else {
                $reference->setOrderState(null);
            }
        }
    }
}
