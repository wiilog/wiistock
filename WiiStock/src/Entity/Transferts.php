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
     * @ORM\Column(type="string", length=255)
     */
    private $statut;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_transfert;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement_debut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement_fin;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones")
     */
    private $zone_debut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones")
     */
    private $zone_fin;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Articles", mappedBy="transferts", cascade={"persist"})
     */
    private $articles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres", inversedBy="transferts")
     */
    private $ordres;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
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

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getDateTransfert(): ?\DateTimeInterface
    {
        return $this->date_transfert;
    }

    public function setDateTransfert(?\DateTimeInterface $date_transfert): self
    {
        $this->date_transfert = $date_transfert;

        return $this;
    }

    public function getEmplacementDebut(): ?Emplacements
    {
        return $this->emplacement_debut;
    }

    public function setEmplacementDebut(?Emplacements $emplacement_debut): self
    {
        $this->emplacement_debut = $emplacement_debut;

        return $this;
    }

    public function getEmplacementFin(): ?Emplacements
    {
        return $this->emplacement_fin;
    }

    public function setEmplacementFin(?Emplacements $emplacement_fin): self
    {
        $this->emplacement_fin = $emplacement_fin;

        return $this;
    }

    public function getZoneDebut(): ?Zones
    {
        return $this->zone_debut;
    }

    public function setZoneDebut(?Zones $zone_debut): self
    {
        $this->zone_debut = $zone_debut;

        return $this;
    }

    public function getZoneFin(): ?Zones
    {
        return $this->zone_fin;
    }

    public function setZoneFin(?Zones $zone_fin): self
    {
        $this->zone_fin = $zone_fin;

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

    /**
     * @return Collection|Articles[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Articles $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->addTransfert($this);
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            $article->removeTransfert($this);
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
