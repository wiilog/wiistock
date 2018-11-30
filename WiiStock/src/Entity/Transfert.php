<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TransfertRepository")
 */
class Transfert
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
    private $statut;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $emplacement_arrivee;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Historique", mappedBy="transfert")
     */
    private $historiques;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="transfert")
     */
    private $contenus;

    public function __construct()
    {
        $this->historiques = new ArrayCollection();
        $this->contenus = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEmplacementArrivee(): ?string
    {
        return $this->emplacement_arrivee;
    }

    public function setEmplacementArrivee(?string $emplacement_arrivee): self
    {
        $this->emplacement_arrivee = $emplacement_arrivee;

        return $this;
    }

    /**
     * @return Collection|Historique[]
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistorique(Historique $historique): self
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques[] = $historique;
            $historique->setTransfert($this);
        }

        return $this;
    }

    public function removeHistorique(Historique $historique): self
    {
        if ($this->historiques->contains($historique)) {
            $this->historiques->removeElement($historique);
            // set the owning side to null (unless already changed)
            if ($historique->getTransfert() === $this) {
                $historique->setTransfert(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Contenu[]
     */
    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenus(Contenu $contenus): self
    {
        if (!$this->contenus->contains($contenus)) {
            $this->contenus[] = $contenus;
            $contenus->setTransfert($this);
        }

        return $this;
    }

    public function removeContenus(Contenu $contenus): self
    {
        if ($this->contenus->contains($contenus)) {
            $this->contenus->removeElement($contenus);
            // set the owning side to null (unless already changed)
            if ($contenus->getTransfert() === $this) {
                $contenus->setTransfert(null);
            }
        }

        return $this;
    }
}
