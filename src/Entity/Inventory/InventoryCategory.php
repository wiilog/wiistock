<?php

namespace App\Entity\Inventory;

use App\Repository\Inventory\InventoryCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryCategoryRepository::class)]
class InventoryCategory {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private ?string $label = null;

    #[ORM\ManyToOne(targetEntity: InventoryFrequency::class, inversedBy: 'categories')]
    private ?InventoryFrequency $frequency = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getFrequency(): ?InventoryFrequency {
        return $this->frequency;
    }

    public function setFrequency(?InventoryFrequency $frequency): self {
        if($this->frequency && $this->frequency !== $frequency) {
            $this->frequency->removeCategory($this);
        }
        $this->frequency = $frequency;
        $frequency?->addCategory($this);

        return $this;
    }
}
