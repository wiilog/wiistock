<?php

namespace App\EventListener;

use App\Entity\MouvementStock;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class StockMovementListener {

    /** @Required */
    public EntityManagerInterface $entityManager;

    public function postPersist(MouvementStock $movement) {
        $type = $movement->getType();
        if (in_array($type, [MouvementStock::TYPE_ENTREE, MouvementStock::TYPE_SORTIE])) {
            $now = new DateTime();

            $reference = $movement->getRefArticle() ?? $movement->getArticle()->getReferenceArticle();

            if ($type === MouvementStock::TYPE_SORTIE) {
                $reference->setLastStockExit($now);
            }
            else if ($type === MouvementStock::TYPE_ENTREE) {
                $reference->setLastStockEntry($now);
            }

            $this->entityManager->flush();
        }
    }
}
