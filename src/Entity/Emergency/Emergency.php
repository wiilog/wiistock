<?php

namespace App\Entity\Emergency;


use App\Entity\Fournisseur;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\EmergencyRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmergencyRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: Types::STRING)]
abstract class Emergency
{
    use FreeFieldsManagerTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $endEmergencyCriteria = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'SET NULL')]
    private ?Type $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $carrierTrackingNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $dateStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $dateEnd = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'emergencies')]
    private ?Fournisseur $provider = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $closedAt = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $command = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'emergencies')]
    private ?Utilisateur $buyer = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class, inversedBy: 'emergencies')]
    private ?Transporteur $carrier = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getEndEmergencyCriteria(): ?string {
        return $this->endEmergencyCriteria;
    }

    public function setEndEmergencyCriteria(string $endEmergencyCriteria): self {
        $this->endEmergencyCriteria = $endEmergencyCriteria;

        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        if($this->carrier && $this->carrier!== $carrier) {
            $this->carrier->removeEmergency($this);
        }
        $this->carrier = $carrier;
        $carrier->addEmergency($this);

        return $this;
    }

    public function getBuyer(): ?Utilisateur {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self {
        if($this->buyer && $this->buyer!== $buyer) {
            $this->buyer->removeEmergency($this);
        }
        $this->buyer = $buyer;
        $buyer->addEmergency($this);

        return $this;
    }

    public function getCommand(): ?string {
        return $this->command;
    }

    public function setCommand(?string $command): self {
        $this->command = $command;

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeInterface {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): self {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getDateEnd(): ?DateTime {
        return $this->dateEnd;
    }

    public function setDateEnd(?DateTime $dateEnd): self {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCarrierTrackingNumber(): ?string {
        return $this->carrierTrackingNumber;
    }

    public function setCarrierTrackingNumber(?string $carrierTrackingNumber): self {
        $this->carrierTrackingNumber = $carrierTrackingNumber;

        return $this;
    }

    public function getDateStart(): ?DateTime {
        return $this->dateStart;
    }

    public function setDateStart(?DateTime $dateStart): self {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getProvider(): ?Fournisseur {
        return $this->provider;
    }

    public function setProvider(?Fournisseur $provider): self {
        $this->provider = $provider;

        return $this;
    }
}
