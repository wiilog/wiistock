<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NatureRepository")
 */
class Nature
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Colis", mappedBy="nature")
     */
    private $colis;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $defaultQuantity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $prefix;

	/**
	 * @ORM\Column(type="string", length=32, nullable=true)
	 */
    private $color;

    public function __construct()
    {
        $this->colis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection|Colis[]
     */
    public function getColis(): Collection
    {
        return $this->colis;
    }

    public function addColis(Colis $coli): self
    {
        if (!$this->colis->contains($coli)) {
            $this->colis[] = $coli;
            $coli->setNature($this);
        }

        return $this;
    }

    public function removeColis(Colis $coli): self
    {
        if ($this->colis->contains($coli)) {
            $this->colis->removeElement($coli);
            // set the owning side to null (unless already changed)
            if ($coli->getNature() === $this) {
                $coli->setNature(null);
            }
        }

        return $this;
    }

    public function getDefaultQuantity(): ?int
    {
        return $this->defaultQuantity;
    }

    public function setDefaultQuantity(?int $defaultQuantity): self
    {
        $this->defaultQuantity = $defaultQuantity;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function addColi(Colis $coli): self
    {
        if (!$this->colis->contains($coli)) {
            $this->colis[] = $coli;
            $coli->setNature($this);
        }

        return $this;
    }

    public function removeColi(Colis $coli): self
    {
        if ($this->colis->contains($coli)) {
            $this->colis->removeElement($coli);
            // set the owning side to null (unless already changed)
            if ($coli->getNature() === $this) {
                $coli->setNature(null);
            }
        }

        return $this;
    }
}
