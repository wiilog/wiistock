<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AcheminementsRepository")
 */
class Acheminements
{
    const CATEGORIE = 'acheminements';
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
     * @ORM\Column(type="json", nullable=true)
     */
    private $packs = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="acheminementsReceive")
     * @ORM\JoinColumn(nullable=false)
     */
    private $receiver;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="acheminementsRequester")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $locationTake;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $locationDrop;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="acheminements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

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

    public function getPacks(): ?array
    {
        return $this->packs;
    }

    public function setPacks(?array $packs): self
    {
        $this->packs = $packs;
        return $this;
    }

    public function getLocationTake(): ?string
    {
        return $this->locationTake;
    }

    public function setLocationTake(string $locationTake): self
    {
        $this->locationTake = $locationTake;

        return $this;
    }

    public function getLocationDrop(): ?string
    {
        return $this->locationDrop;
    }

    public function setLocationDrop(string $locationDrop): self
    {
        $this->locationDrop = $locationDrop;

        return $this;
    }

    public function getReceiver(): ?Utilisateur
    {
        return $this->receiver;
    }

    public function setReceiver(?Utilisateur $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

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
}
