<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesTransfertsRepository")
 */
class DemandesTransferts
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

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
     * @ORM\OneToOne(targetEntity="App\Entity\Demandes", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    public function getId()
    {
        return $this->id;
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

    public function getDemande(): ?Demandes
    {
        return $this->demande;
    }

    public function setDemande(Demandes $demande): self
    {
        $this->demande = $demande;

        return $this;
    }
}
