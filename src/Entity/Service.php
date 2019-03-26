<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ServiceRepository")
 */
class Service
{
    const CATEGORIE = 'service';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_TRAITE = 'traité';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $libelle;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="services")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demandeur;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="services")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\emplacement", inversedBy="services")
     * @ORM\JoinColumn(nullable=false)
     */
    private $emplacement;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

       public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDemandeur(): ?utilisateur
    {
        return $this->demandeur;
    }

    public function setDemandeur(?utilisateur $demandeur): self
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEmplacement(): ?emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }
}
