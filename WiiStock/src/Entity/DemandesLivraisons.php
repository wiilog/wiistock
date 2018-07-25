<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesLivraisonsRepository")
 */
class DemandesLivraisons
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
    private $date_livraison;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $adresse_livraison;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Demandes", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    public function getId()
    {
        return $this->id;
    }

    public function getDateLivraison(): ?\DateTimeInterface
    {
        return $this->date_livraison;
    }

    public function setDateLivraison(\DateTimeInterface $date_livraison): self
    {
        $this->date_livraison = $date_livraison;

        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresse_livraison;
    }

    public function setAdresseLivraison(string $adresse_livraison): self
    {
        $this->adresse_livraison = $adresse_livraison;

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
