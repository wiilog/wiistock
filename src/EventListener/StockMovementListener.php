<?php

namespace App\EventListener;

use App\Entity\MouvementStock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class StockMovementListener {

    /** @Required */
    public EntityManagerInterface $entityManager;

    public function postPersist(MouvementStock $movement) {
        $now = new DateTime();

        $type = $movement->getType();
        $reference = $movement->getRefArticle() ?? $movement->getArticle()->getReferenceArticle();

        if($type === MouvementStock::TYPE_SORTIE) {
            $reference->setLastStockExit($now);
        } elseif($type === MouvementStock::TYPE_ENTREE) {
            $reference->setLastStockEntry($now);
        }

        $this->entityManager->flush();
    }
}
