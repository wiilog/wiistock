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
