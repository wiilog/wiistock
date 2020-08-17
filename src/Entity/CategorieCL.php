<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CategorieCLRepository")
 */
class CategorieCL
{

    const REFERENCE_ARTICLE = 'référence article';
    const ARTICLE = 'article';
    const RECEPTION = 'réception';
    const DEMANDE_LIVRAISON = 'demande livraison';
    const DEMANDE_ACHEMINEMENT = 'acheminements';
    const DEMANDE_COLLECTE = 'demande collecte';
    const ARRIVAGE = 'arrivage';
    const MVT_TRACA = 'mouvement traca';
    const AUCUNE = 'aucune';

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
     * @ORM\OneToMany(targetEntity="App\Entity\ChampLibre", mappedBy="categorieCL")
     */
    private $champsLibres;

    public function __construct()
    {
        $this->champsLibres = new ArrayCollection();
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

    /**
     * @return Collection|ChampLibre[]
     */
    public function getChampsLibres(): Collection
    {
        return $this->champsLibres;
    }

    public function addChampLibre(ChampLibre $champLibre): self
    {
        if (!$this->champsLibres->contains($champLibre)) {
            $this->champsLibres[] = $champLibre;
            $champLibre->setCategorieCL($this);
        }

        return $this;
    }

    public function removeChampLibre(ChampLibre $champLibre): self
    {
        if ($this->champsLibres->contains($champLibre)) {
            $this->champsLibres->removeElement($champLibre);
            // set the owning side to null (unless already changed)
            if ($champLibre->getCategorieCL() === $this) {
                $champLibre->setCategorieCL(null);
            }
        }

        return $this;
    }

    public function addChampsLibre(ChampLibre $champsLibre): self
    {
        if (!$this->champsLibres->contains($champsLibre)) {
            $this->champsLibres[] = $champsLibre;
            $champsLibre->setCategorieCL($this);
        }

        return $this;
    }

    public function removeChampsLibre(ChampLibre $champsLibre): self
    {
        if ($this->champsLibres->contains($champsLibre)) {
            $this->champsLibres->removeElement($champsLibre);
            // set the owning side to null (unless already changed)
            if ($champsLibre->getCategorieCL() === $this) {
                $champsLibre->setCategorieCL(null);
            }
        }

        return $this;
    }
}
