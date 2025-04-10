<?php

namespace App\Entity\Emergency;


use App\Entity\Fournisseur;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Repository\Emergency\EmergencyRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmergencyRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: Types::STRING)]
#[ORM\DiscriminatorMap([
    EmergencyDiscrEnum::STOCK_EMERGENCY->value => StockEmergency::class,
    EmergencyDiscrEnum::TRACKING_EMERGENCY->value => TrackingEmergency::class,
])]
#[ORM\Index(fields: ["dateStart"])]
#[ORM\Index(fields: ["dateEnd"])]
#[ORM\Index(fields: ["createdAt"])]
#[ORM\Index(fields: ["closedAt"])]
#[ORM\Index(fields: ["endEmergencyCriteria"])]
abstract class Emergency {
    use FreeFieldsManagerTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, nullable: false, enumType: EndEmergencyCriteriaEnum::class)]
    private ?EndEmergencyCriteriaEnum $endEmergencyCriteria = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $carrierTrackingNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $dateEnd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $closedAt = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $command = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fournisseur $supplier = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $buyer = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Transporteur $carrier = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getEndEmergencyCriteria(): ?EndEmergencyCriteriaEnum {
        return $this->endEmergencyCriteria;
    }

    public function setEndEmergencyCriteria(?EndEmergencyCriteriaEnum $endEmergencyCriteria): self {
        $this->endEmergencyCriteria = $endEmergencyCriteria;

        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        $this->carrier = $carrier;;

        return $this;
    }

    public function getBuyer(): ?Utilisateur {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self {
        $this->buyer = $buyer;

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

    public function setType(Type $type): self {
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

    public function getSupplier(): ?Fournisseur {
        return $this->supplier;
    }

    public function setSupplier(?Fournisseur $supplier): self {
        $this->supplier = $supplier;

        return $this;
    }
}
