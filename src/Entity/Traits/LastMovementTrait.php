<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\MouvementStock;

trait LastMovementTrait {

    public const LAST_MOVEMENT_TYPES = [
        MouvementStock::TYPE_ENTREE,
        MouvementStock::TYPE_SORTIE,
    ];

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?MouvementStock $lastMovement = null;

    public function getLastMovement(): ?MouvementStock {
        return $this->lastMovement;
    }

    public function setLastMovement(?MouvementStock $lastMovement): static {
        $this->lastMovement = $lastMovement;

        return $this;
    }
}
