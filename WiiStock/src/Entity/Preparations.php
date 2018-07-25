<?php

namespace App\Entity;

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
     * @ORM\JoinColumn(nullable=false)
     */
    private $quai_preparation;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Clients")
     * @ORM\JoinColumn(nullable=false)
     */
    private $client;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CommandesClients")
     */
    private $commande_client;

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

    public function setDatePreparation(\DateTimeInterface $date_preparation): self
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

    public function getHistorique(): ?Historiques
    {
        return $this->historique;
    }

    public function setHistorique(?Historiques $historique): self
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
