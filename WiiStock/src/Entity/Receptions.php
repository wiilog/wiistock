<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionsRepository")
 */
class Receptions
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
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais")
     */
    private $quai_reception;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseurs")
     */
    private $fournisseur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CommandesFournisseurs")
     */
    private $commande_fournisseur;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres", inversedBy="receptions")
     */
    private $ordres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Entrees", mappedBy="reception")
     */
    private $entrees;

    public function __construct()
    {
        $this->entrees = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getQuaiReception(): ?Quais
    {
        return $this->quai_reception;
    }

    public function setQuaiReception(?Quais $quai_reception): self
    {
        $this->quai_reception = $quai_reception;

        return $this;
    }

    public function getDateReception(): ?\DateTimeInterface
    {
        return $this->date_reception;
    }

    public function setDateReception(?\DateTimeInterface $date_reception): self
    {
        $this->date_reception = $date_reception;

        return $this;
    }

    public function getFournisseur(): ?Fournisseurs
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseurs $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getCommandeFournisseur(): ?CommandesFournisseurs
    {
        return $this->commande_fournisseur;
    }

    public function setCommandeFournisseur(?CommandesFournisseurs $commande_fournisseur): self
    {
        $this->commande_fournisseur = $commande_fournisseur;

        return $this;
    }

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(Historiques $historique): self
    {
        $this->historique = $historique;

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

    /**
     * @return Collection|Entrees[]
     */
    public function getEntrees(): Collection
    {
        return $this->entrees;
    }

    public function addEntree(Entrees $entree): self
    {
        if (!$this->entrees->contains($entree)) {
            $this->entrees[] = $entree;
            $entree->setReception($this);
        }

        return $this;
    }

    public function removeEntree(Entrees $entree): self
    {
        if ($this->entrees->contains($entree)) {
            $this->entrees->removeElement($entree);
            // set the owning side to null (unless already changed)
            if ($entree->getReception() === $this) {
                $entree->setReception(null);
            }
        }

        return $this;
    }
}
