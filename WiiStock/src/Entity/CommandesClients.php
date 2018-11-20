<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommandesClientsRepository")
 */
class CommandesClients
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
    private $libelle;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_commande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Clients")
     */
    private $client;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $n_commande;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $n_affaire;

    public function getId()
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->date_commande;
    }

    public function setDateCommande(?\DateTimeInterface $date_commande): self
    {
        $this->date_commande = $date_commande;

        return $this;
    }

    public function getClient(): ?Clients
    {
        return $this->client;
    }

    public function setClient(?Clients $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getNCommande(): ?string
    {
        return $this->n_commande;
    }

    public function setNCommande(?string $n_commande): self
    {
        $this->n_commande = $n_commande;

        return $this;
    }

    public function getNAffaire(): ?string
    {
        return $this->n_affaire;
    }

    public function setNAffaire(?string $n_affaire): self
    {
        $this->n_affaire = $n_affaire;

        return $this;
    }
}
