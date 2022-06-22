<?php

namespace App\Entity\Inventory;

use App\Repository\Inventory\InventoryFrequencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryFrequencyRepository::class)]
class InventoryFrequency {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nbMonths = null;

    #[ORM\OneToMany(mappedBy: 'frequency', targetEntity: InventoryCategory::class)]
    private Collection $categories;

    public function __construct() {
        $this->categories = new ArrayCollection();
    }

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

    public function getNbMonths(): ?int {
        return $this->nbMonths;
    }

    public function setNbMonths(int $nbMonths): self {
        $this->nbMonths = $nbMonths;

        return $this;
    }

    /**
     * @return Collection<int, InventoryCategory>
     */
    public function getCategories(): Collection {
        return $this->categories;
    }

    public function addCategory(InventoryCategory $category): self {
        if(!$this->categories->contains($category)) {
            $this->categories[] = $category;
            $category->setFrequency($this);
        }

        return $this;
    }

    public function removeCategory(InventoryCategory $category): self {
        if($this->categories->contains($category)) {
            $this->categories->removeElement($category);
            // set the owning side to null (unless already changed)
            if($category->getFrequency() === $this) {
                $category->setFrequency(null);
            }
        }

        return $this;
    }

}
