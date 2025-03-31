<?php

namespace App\Entity\Traits;

use App\Controller\SleepingStock\IndexController;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\MouvementStock;

trait LastMovementTrait {
    /**
     * @var MouvementStock|null
     * Attribute only used for SleepingStock Feature.
     * @see IndexController
     *
     * Use for calculating the last time an article or a reference article was intentionally moved
     * that why only type TYPE_SORTIE and TYPE_ENTREE are allowed
     * @see MouvementStockService::LAST_MOVEMENT_TYPES
     * @see MouvementStockService::createMouvementStock
     */
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?MouvementStock $lastMovement = null;

    public function getLastMovement(): ?MouvementStock {
        return $this->lastMovement;
    }

    public function setLastMovement(?MouvementStock $lastMovement): static {
        $this->lastMovement = $lastMovement;

        return $this;
    }
}
