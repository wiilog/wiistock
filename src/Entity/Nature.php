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
     * @ORM\OneToMany(targetEntity="App\Entity\Pack", mappedBy="nature")
     */
    private $packs;

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

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $needsMobileSync;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Emplacement", mappedBy="allowedNatures")
     */
    private $emplacements;

    public function __construct()
    {
        $this->packs = new ArrayCollection();
        $this->emplacements = new ArrayCollection();
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
     * @return Collection|Pack[]
     */
    public function getPacks(): Collection
    {
        return $this->packs;
    }

    public function addPack(Pack $pack): self
    {
        if (!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setNature($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self
    {
        if ($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
            // set the owning side to null (unless already changed)
            if ($pack->getNature() === $this) {
                $pack->setNature(null);
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getNeedsMobileSync(): ?bool
    {
        return $this->needsMobileSync;
    }

    public function setNeedsMobileSync(?bool $needsMobileSync): self
    {
        $this->needsMobileSync = $needsMobileSync;

        return $this;
    }

    /**
     * @return Collection|Emplacement[]
     */
    public function getEmplacements(): Collection
    {
        return $this->emplacements;
    }

    public function addEmplacement(Emplacement $emplacement): self
    {
        if (!$this->emplacements->contains($emplacement)) {
            $this->emplacements[] = $emplacement;
            $emplacement->addAllowedNature($this);
        }

        return $this;
    }

    public function removeEmplacement(Emplacement $emplacement): self
    {
        if ($this->emplacements->contains($emplacement)) {
            $this->emplacements->removeElement($emplacement);
            $emplacement->removeAllowedNature($this);
        }

        return $this;
    }
}
