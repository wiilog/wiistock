<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PreparationsRepository")
 */
class Preparations
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
    private $quai_preparation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Clients")
     */
    private $client;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CommandesClients")
     */
    private $commande_client;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Ordres", inversedBy="preparations")
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

    public function getQuaiPreparation(): ?Quais
    {
        return $this->quai_preparation;
    }

    public function setQuaiPreparation(?Quais $quai_preparation): self
    {
        $this->quai_preparation = $quai_preparation;

        return $this;
    }

    public function getDatePreparation(): ?\DateTimeInterface
    {
        return $this->date_preparation;
    }

    public function setDatePreparation(?\DateTimeInterface $date_preparation): self
    {
        $this->date_preparation = $date_preparation;

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

    public function getCommandeClient(): ?CommandesClients
    {
        return $this->commande_client;
    }

    public function setCommandeClient(?CommandesClients $commande_client): self
    {
        $this->commande_client = $commande_client;

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
