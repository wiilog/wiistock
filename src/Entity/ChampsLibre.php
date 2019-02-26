<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChampsLibreRepository")
 */
class ChampsLibre
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="champsLibres")
     */
    private $type;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampsLibre", mappedBy="champsLibre")
     */
    private $valeurChampsLibres;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $typage;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $defaultValue;

    public function __construct()
    {
        $this->valeurChampsLibres = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->label;
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

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection|ValeurChampsLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addChampsLibre($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampsLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeChampsLibre($this);
        }

        return $this;
    }

    public function getTypage(): ?string
    {
        return $this->typage;
    }

    public function setTypage(?string $typage): self
    {
        $this->typage = $typage;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }
}
