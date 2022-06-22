<?php

namespace App\Entity\Inventory;

use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Repository\Inventory\InventoryCategoryHistoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryCategoryHistoryRepository::class)]
class InventoryCategoryHistory {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $inventoryCategoryBefore;

    #[ORM\ManyToOne(targetEntity: InventoryCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $inventoryCategoryAfter;

    #[ORM\Column(type: 'date')]
    private $date;

    #[ORM\OneToMany(targetEntity: Utilisateur::class, mappedBy: 'inventoryCategoryHistory')]
    private $operator;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'inventoryCategoryHistory')]
    private $refArticle;

    public function __construct() {
        $this->operator = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getInventoryCategoryBefore(): ?InventoryCategory {
        return $this->inventoryCategoryBefore;
    }

    public function setInventoryCategoryBefore(?InventoryCategory $inventoryCategoryBefore): self {
        $this->inventoryCategoryBefore = $inventoryCategoryBefore;

        return $this;
    }

    public function getInventoryCategoryAfter(): ?InventoryCategory {
        return $this->inventoryCategoryAfter;
    }

    public function setInventoryCategoryAfter(?InventoryCategory $inventoryCategoryAfter): self {
        $this->inventoryCategoryAfter = $inventoryCategoryAfter;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getOperator(): Collection {
        return $this->operator;
    }

    public function addOperator(Utilisateur $operator): self {
        if(!$this->operator->contains($operator)) {
            $this->operator[] = $operator;
            $operator->setInventoryCategoryHistory($this);
        }

        return $this;
    }

    public function removeOperator(Utilisateur $operator): self {
        if($this->operator->contains($operator)) {
            $this->operator->removeElement($operator);
            // set the owning side to null (unless already changed)
            if($operator->getInventoryCategoryHistory() === $this) {
                $operator->setInventoryCategoryHistory(null);
            }
        }

        return $this;
    }

    public function getRefArticle(): ?ReferenceArticle {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self {
        $this->refArticle = $refArticle;

        return $this;
    }

}
