<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TransfertsRepository")
 */
class Transferts
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
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="transfert")
     */
    private $contenus;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Historiques", mappedBy="transfert")
     */
    private $historiques;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres", inversedBy="transferts")
     */
    private $ordres;

    public function __construct()
    {
        $this->contenus = new ArrayCollection();
        $this->historiques = new ArrayCollection();
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

    /**
     * @return Collection|Historiques[]
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistorique(Historiques $historique): self
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques[] = $historique;
            $historique->setTransfert($this);
        }

        return $this;
    }

    public function removeHistorique(Historiques $historique): self
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

    public function getOrdres(): ?Ordres
    {
        return $this->ordres;
    }

    public function setOrdres(?Ordres $ordres): self
    {
        $this->ordres = $ordres;

        return $this;
    }
}
