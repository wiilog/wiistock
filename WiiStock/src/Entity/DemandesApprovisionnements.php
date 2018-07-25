<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesApprovisionnementsRepository")
 */
class DemandesApprovisionnements
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
    private $date_approvisionnement;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Demandes", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    public function getId()
    {
        return $this->id;
    }

    public function getDateApprovisionnement(): ?\DateTimeInterface
    {
        return $this->date_approvisionnement;
    }

    public function setDateApprovisionnement(\DateTimeInterface $date_approvisionnement): self
    {
        $this->date_approvisionnement = $date_approvisionnement;

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
