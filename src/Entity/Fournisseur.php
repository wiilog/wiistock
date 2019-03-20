<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FournisseurRepository")
 */
class Fournisseur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $codeReference;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="fournisseur")
     */
    private $receptions;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
    }

    public function getId() : ? int
    {
        return $this->id;
    }

    public function getCodeReference() : ? string
    {
        return $this->codeReference;
    }

    public function setCodeReference(? string $codeReference) : self
    {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getNom() : ? string
    {
        return $this->nom;
    }

    public function setNom(? string $nom) : self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions() : Collection
    {
        return $this->receptions;
    }

    public function addReceptions(Reception $reception) : self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptions(Reception $reception) : self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }
}
