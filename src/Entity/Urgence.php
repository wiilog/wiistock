<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use App\Repository\UrgenceRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UrgenceRepository::class)]
class Urgence {

    public const ARRIVAL_EMERGENCY_TRIGGERING_FIELDS = [
        "provider" => "Fournisseur",
        "carrier" => "Transporteur",
        "commande" => "Numéro de commande",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $dateStart = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $dateEnd = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $createdAt;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $commande = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'emergencies')]
    private ?Utilisateur $buyer = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'emergencies')]
    private ?Fournisseur $provider = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'emergencies')]
    private ?Transporteur $carrier = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $trackingNb = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $postNb = null;

    #[ORM\ManyToOne(targetEntity: Arrivage::class, inversedBy: 'urgences')]
    private ?Arrivage $lastArrival = null;

    public function __construct() {
        $this->createdAt = new DateTime('now');
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDateStart(): ?DateTime {
        return $this->dateStart;
    }

    public function setDateStart(DateTime $dateStart): self {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?DateTime {
        return $this->dateEnd;
    }

    public function setDateEnd(DateTime $dateEnd): self {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getCommande(): ?string {
        return $this->commande;
    }

    public function setCommande(string $commande): self {
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

    public function getTrackingNb(): ?string {
        return $this->trackingNb;
    }

    public function setTrackingNb(string $trackingNb): self {
        $this->trackingNb = $trackingNb;

        return $this;
    }

    public function getPostNb(): ?string {
        return $this->postNb;
    }

    public function setPostNb(string $postNb): self {
        $this->postNb = $postNb;

        return $this;
    }

    public function getProvider(): ?Fournisseur {
        return $this->provider;
    }

    public function setProvider(?Fournisseur $provider): self {
        $this->provider = $provider;

        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        $this->carrier = $carrier;

        return $this;
    }

    public function getLastArrival(): ?Arrivage {
        return $this->lastArrival;
    }

    public function setLastArrival(?Arrivage $lastArrival): self {
        $this->lastArrival = $lastArrival;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function serialize() {
        return [
            'dateStart' => FormatHelper::datetime($this->getDateStart()),
            'dateEnd' => FormatHelper::datetime($this->getDateEnd()),
            'commande' => $this->getCommande() ?: '',
            'numposte' => $this->getPostNb() ?: '',
            'buyer' => FormatHelper::user($this->getBuyer()),
            'provider' => FormatHelper::supplier($this->getProvider()),
            'carrier' => $this->getCarrier() ? $this->getCarrier()->getLabel() : '',
            'trackingnum' => $this->getTrackingNb() ?: '',
            'datearrival' => $this->getLastArrival() ? FormatHelper::datetime($this->getLastArrival()->getDate()) : '',
            'arrivageNumber' => $this->getLastArrival() ? $this->getLastArrival()->getNumeroArrivage() : '',
            'creationDate' => FormatHelper::datetime($this->getCreatedAt()),
        ];
    }

}
