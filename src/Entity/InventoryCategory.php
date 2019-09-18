<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InventoryCategoryRepository")
 */
class InventoryCategory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $label;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\InventoryFrequency")
     */
    private $frequency;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="category")
     */
    private $refArticle;

    /**
     * @ORM\Column(type="boolean")
     */
    private $permanent;

    public function __construct()
    {
        $this->frequency = new ArrayCollection();
        $this->refArticle = new ArrayCollection();
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
     * @return Collection|InventoryFrequency[]
     */
    public function getFrequency(): Collection
    {
        return $this->frequency;
    }

    public function addFrequency(InventoryFrequency $frequency): self
    {
        if (!$this->frequency->contains($frequency)) {
            $this->frequency[] = $frequency;
        }

        return $this;
    }

    public function removeFrequency(InventoryFrequency $frequency): self
    {
        if ($this->frequency->contains($frequency)) {
            $this->frequency->removeElement($frequency);
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getRefArticle(): Collection
    {
        return $this->refArticle;
    }

    public function addRefArticle(ReferenceArticle $refArticle): self
    {
        if (!$this->refArticle->contains($refArticle)) {
            $this->refArticle[] = $refArticle;
            $refArticle->setCategory($this);
        }

        return $this;
    }

    public function removeRefArticle(ReferenceArticle $refArticle): self
    {
        if ($this->refArticle->contains($refArticle)) {
            $this->refArticle->removeElement($refArticle);
            // set the owning side to null (unless already changed)
            if ($refArticle->getCategory() === $this) {
                $refArticle->setCategory(null);
            }
        }

        return $this;
    }

    public function getPermanent(): ?bool
    {
        return $this->permanent;
    }

    public function setPermanent(bool $permanent): self
    {
        $this->permanent = $permanent;

        return $this;
    }

    public function setFrequency(?InventoryFrequency $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }
}
