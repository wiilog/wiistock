<?php

namespace App\Entity;

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
     * @ORM\Column(type="datetime")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Articles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $article;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Historiques", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $historique;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Ordres", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $ordre;

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

    public function setDateTransfert(\DateTimeInterface $date_transfert): self
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

    public function getArticle(): ?Articles
    {
        return $this->article;
    }

    public function setArticle(?Articles $article): self
    {
        $this->article = $article;

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

    public function getOrdre(): ?Ordres
    {
        return $this->ordre;
    }

    public function setOrdre(Ordres $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }
}
