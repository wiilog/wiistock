<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UrgenceRepository")
 */
class Urgence
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
    private $dateStart;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateEnd;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $commande;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="emergencies")
     */
    private $buyer;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur")
     */
    private $provider;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur")
     */
    private $carrier;

    /**
     * @ORM\Column(type="string", length=255, nullable=true, nullable=true)
     */
    private $trackingNb;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $postNb;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="urgences")
     */
    private $lastArrival;

    public function __construct()
    {
        $this->createdAt = (new DateTime('NOW'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateStart(): ?DateTime
    {
        return $this->dateStart;
    }

    public function setDateStart(DateTime $dateStart): self
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?DateTime
    {
        return $this->dateEnd;
    }

    public function setDateEnd(DateTime $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getCommande(): ?string
    {
        return $this->commande;
    }

    public function setCommande(string $commande): self
    {
        $this->commande = $commande;

        return $this;
    }

    public function getBuyer(): ?Utilisateur {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self {
        $this->buyer = $buyer;
        return $this;
    }

    public function getTrackingNb(): ?string
    {
        return $this->trackingNb;
    }

    public function setTrackingNb(string $trackingNb): self
    {
        $this->trackingNb = $trackingNb;

        return $this;
    }

    public function getPostNb(): ?string
    {
        return $this->postNb;
    }

    public function setPostNb(string $postNb): self
    {
        $this->postNb = $postNb;

        return $this;
    }

    public function getProvider(): ?Fournisseur
    {
        return $this->provider;
    }

    public function setProvider(?Fournisseur $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getCarrier(): ?Transporteur
    {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getLastArrival(): ?Arrivage
    {
        return $this->lastArrival;
    }

    public function setLastArrival(?Arrivage $lastArrival): self
    {
        $this->lastArrival = $lastArrival;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     * @return Urgence
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function serialize()
    {
        return [
            'dateStart' => $this->getDateStart() ? $this->getDateStart()->format('d/m/Y H:i:s') : '',
            'dateEnd' => $this->getDateEnd() ? $this->getDateEnd()->format('d/m/Y H:i:s') : '',
            'commande' => $this->getCommande() ? $this->getCommande() : '',
            'numposte' => $this->getPostNb() ? $this->getPostNb() : '',
            'buyer' => $this->getBuyer() ? $this->getBuyer()->getUsername() : '',
            'provider' => $this->getProvider() ? $this->getProvider()->getNom() : '',
            'carrier' => $this->getCarrier() ? $this->getCarrier()->getLabel() : '',
            'trackingnum' => $this->getTrackingNb() ? $this->getTrackingNb() : '',
            'datearrival' => $this->getLastArrival() ? $this->getLastArrival()->getDate()->format('d/m/Y H:i:s') : '',
            'arrivageNumber' => $this->getLastArrival() ? $this->getLastArrival()->getNumeroArrivage() : '',
            'creationDate' => $this->getCreatedAt() ? $this->getCreatedAt()->format('d/m/Y H:i:s') : '',
        ];
    }
}
