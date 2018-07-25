<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandesRepository")
 */
class Demandes
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
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_demande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $auteur;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDateDemande(): ?\DateTimeInterface
    {
        return $this->date_demande;
    }

    public function setDateDemande(\DateTimeInterface $date_demande): self
    {
        $this->date_demande = $date_demande;

        return $this;
    }

    public function getAuteur(): ?Utilisateurs
    {
        return $this->auteur;
    }

    public function setAuteur(?Utilisateurs $auteur): self
    {
        $this->auteur = $auteur;

        return $this;
    }
}
