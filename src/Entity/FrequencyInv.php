<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FrenquencyInvRepository")
 */
class FrequencyInv
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
     * @ORM\OneToMany(targetEntity="App\Entity\CategoryInv", mappedBy="frequency")
     */
    private $categoryInvs;

    public function __construct()
    {
        $this->categoryInvs = new ArrayCollection();
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

    /**
     * @return Collection|CategoryInv[]
     */
    public function getCategoryInvs(): Collection
    {
        return $this->categoryInvs;
    }

    public function addCategoryInv(CategoryInv $categoryInv): self
    {
        if (!$this->categoryInvs->contains($categoryInv)) {
            $this->categoryInvs[] = $categoryInv;
            $categoryInv->addFrequency($this);
        }

        return $this;
    }

    public function removeCategoryInv(CategoryInv $categoryInv): self
    {
        if ($this->categoryInvs->contains($categoryInv)) {
            $this->categoryInvs->removeElement($categoryInv);
            $categoryInv->removeFrequency($this);
        }

        return $this;
    }
}
