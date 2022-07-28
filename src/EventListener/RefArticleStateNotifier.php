<?php

namespace App\EventListener;

use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleStateNotifier {

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public RefArticleDataService $refService;

    public function postPersist($entity) {
        $this->handleLinks($entity);
    }

    public function postUpdate($entity) {
        $this->handleLinks($entity);
    }

    private function handleLinks($entity) {

        if (!($entity instanceof Reception)
            && !($entity instanceof PurchaseRequest)) {
            return;
        }

        $receptionReferenceArticleRepository = $this->entityManager->getRepository(ReceptionReferenceArticle::class);
        $purchaseRequestLineRepository = $this->entityManager->getRepository(PurchaseRequestLine::class);

        if ($entity instanceof Reception) {
            $status = $entity->getStatut() ? $entity->getStatut()->getCode() : null;
            $receptionReferenceArticles = $entity->getReceptionReferenceArticles();

            if ($status === Reception::STATUT_RECEPTION_PARTIELLE){
                foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
                    $reference = $receptionReferenceArticle->getReferenceArticle();

                    if ($receptionReferenceArticle->getQuantite() !== $receptionReferenceArticle->getQuantiteAR()) {
                        $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                    } else{
                        $this->refService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                    }
                }
            } else if ($status === Reception::STATUT_EN_ATTENTE ) {
                foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
                    $reference = $receptionReferenceArticle->getReferenceArticle();
                    $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                }
            } else {
                foreach ($entity->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                    $reference = $receptionReferenceArticle->getReferenceArticle();
                    $this->refService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
            }
        } else if ($entity instanceof PurchaseRequest) {
            $status = $entity->getStatus() ? $entity->getStatus()->getState() : null;
            $lines = $entity->getPurchaseRequestLines();

            if ($status === Statut::NOT_TREATED || $status === Statut::IN_PROGRESS) {
                foreach ($lines as $line) {
                    $reference = $line->getReference();
                    $associatedLines = $receptionReferenceArticleRepository->findByReferenceArticleAndReceptionStatus(
                        $reference,
                        [Reception::STATUT_EN_ATTENTE, Reception::STATUT_RECEPTION_PARTIELLE]
                    );
                    if (empty($associatedLines)) {
                        $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
                    } else {
                        $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                    }
                }
            } else {
                foreach ($lines as $line) {
                    $reference = $line->getReference();
                    $this->refService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
            }
        }
        $this->entityManager->flush();
    }
}
