<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Repository\Transport\TransportCollectRequestLineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportCollectRequestLineRepository::class)]
class TransportCollectRequestLine extends TransportRequestLine {

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantityToCollect = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $collectedQuantity = null;

    public function getQuantityToCollect(): ?int {
        return $this->quantityToCollect;
    }

    public function setQuantityToCollect(?int $quantityToCollect): self {
        $this->quantityToCollect = $quantityToCollect;

        return $this;
    }

    public function getCollectedQuantity(): ?int {
        return $this->collectedQuantity;
    }

    public function setCollectedQuantity(?int $collectedQuantity): self {
        $this->collectedQuantity = $collectedQuantity;

        return $this;
    }
}
