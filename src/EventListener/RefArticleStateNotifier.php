<?php

namespace App\EventListener;

use App\Entity\PurchaseRequest;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleStateNotifier {

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function postUpdate($entity) {
        if ($entity instanceof Reception) {
            $status = $entity->getStatut() ? $entity->getStatut()->getNom() : null;
            $receptionReferenceArticles = $entity->getReceptionReferenceArticles();
            if(!$receptionReferenceArticles->isEmpty()) {
                foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
                    $purchaseRequestLines = $receptionReferenceArticle->getReferenceArticle()->getPurchaseRequestLines();
                    if($purchaseRequestLines->isEmpty()) {
                        $reference = $receptionReferenceArticle->getReferenceArticle();
                        $reference->setOrderState(null);
                    }
                }
            }
            if ($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                if(!$entity->getReceptionReferenceArticles()->isEmpty()) {
                    foreach ($entity->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                        $reference = $receptionReferenceArticle->getReferenceArticle();
                        if($reference->getOrderState() === ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE) {
                            $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                        }
                    }
                }
            } else {
                if(!$entity->getReceptionReferenceArticles()->isEmpty()) {
                    foreach ($entity->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                        $reference = $receptionReferenceArticle->getReferenceArticle();
                        $reference->setOrderState(null);
                    }
                }
            }
        } else if ($entity instanceof PurchaseRequest) {
            $status = $entity->getStatus() ? $entity->getStatus()->getState() : null;
            $purchaseRequestLines = $entity->getPurchaseRequestLines();
            if ($status === Statut::NOT_TREATED || $status === Statut::IN_PROGRESS) {
                foreach ($purchaseRequestLines as $purchaseRequestLine) {
                    $reference = $purchaseRequestLine->getReference();
                    if(!$reference->getReceptionReferenceArticles()->isEmpty()) {
                        foreach ($reference->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                            $receptionStatus = $receptionReferenceArticle->getReception() ?
                                $receptionReferenceArticle->getReception()->getStatut()->getNom() : null;
                            if ($receptionStatus === Reception::STATUT_EN_ATTENTE || $receptionStatus === Reception::STATUT_RECEPTION_PARTIELLE) {
                                $reference->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                            } else {
                                $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
                            }
                        }
                    } else {
                        $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
                    }
                }
                $this->entityManager->flush();
            } else {
                foreach ($purchaseRequestLines as $purchaseRequestLine) {
                    $reference = $purchaseRequestLine->getReference();
                    if($reference->getReceptionReferenceArticles()->isEmpty()) {
                        $reference->setOrderState(null);
                    }
                }
            }
        }
    }

    public function postRemove($entity) {
        if ($entity instanceof Reception) {
            foreach ($entity->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                $referenceReceptionReferenceArticles = $receptionReferenceArticle->getReferenceArticle()->getReceptionReferenceArticles();
                foreach ($referenceReceptionReferenceArticles as $referenceReceptionReferenceArticle) {
                    $reception = $referenceReceptionReferenceArticle->getReception();
                    $status = $reception->getStatut()->getNom();
                    if($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                        $reference = $receptionReferenceArticle->getReferenceArticle();
                        if (!$reference->getPurchaseRequestLines()->isEmpty()) {
                            foreach ($reference->getPurchaseRequestLines() as $purchaseRequestLine) {
                                $status = $purchaseRequestLine->getStatut()->getState();
                                if ($status === Statut::NOT_TREATED || $status === Statut::IN_PROGRESS) {
                                    $reference->setOrderState(ReferenceArticle::PURCHASE_IN_PROGRESS_ORDER_STATE);
                                } else {
                                    $reference->setOrderState(null);
                                }
                            }
                        }
                    }
                }
            }
            $this->entityManager->flush();
        }
        else if ($entity instanceof PurchaseRequest) {
            $purchaseRequestLines = $entity->getPurchaseRequestLines();
            foreach ($purchaseRequestLines as $purchaseRequestLine) {
                $reference = $purchaseRequestLine->getReference();
                if(!$reference->getReceptionReferenceArticles()->isEmpty()) {
                    foreach ($reference->getReceptionReferenceArticles() as $receptionReferenceArticle) {
                        $receptionStatus = $receptionReferenceArticle->getReception() ? $receptionReferenceArticle->getReception()->getStatut()->getState() : null;
                        if ($receptionStatus !== Statut::NOT_TREATED || $receptionStatus !== Statut::PARTIAL) {
                            $reference->setOrderState(null);
                        }
                    }
                } else {
                    $reference->setOrderState(null);
                }
                $this->entityManager->flush();
            }
        }
    }
}
