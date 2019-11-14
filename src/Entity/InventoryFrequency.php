<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventoryFrequencyRepository")
 */
class InventoryFrequency
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

	/**
	 * @ORM\Column(type="integer")
	 */
    private $nbMonths;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\InventoryCategory", mappedBy="frequency")
	 */
	private $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getNbMonths(): ?int
    {
        return $this->nbMonths;
    }

    public function setNbMonths(int $nbMonths): self
    {
        $this->nbMonths = $nbMonths;

        return $this;
    }

    /**
     * @return Collection|InventoryCategory[]
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(InventoryCategory $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories[] = $category;
            $category->setFrequency($this);
        }

        return $this;
    }

    public function removeCategory(InventoryCategory $category): self
    {
        if ($this->categories->contains($category)) {
            $this->categories->removeElement($category);
            // set the owning side to null (unless already changed)
            if ($category->getFrequency() === $this) {
                $category->setFrequency(null);
            }
        }

        return $this;
    }

}
