<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\TrackingMovement;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Repository\ShippingRequest\ShippingRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ShippingRequestRepository::class)]
class ShippingRequest extends StatusHistoryContainer {

    public const SHIPMENT_NATIONAL = "national";
    public const SHIPMENT_INTERNATIONAL = "international";

    public const SHIPMENT_LABELS = [
        self::SHIPMENT_NATIONAL => "National",
        self::SHIPMENT_INTERNATIONAL => "International",
    ];

    public const CARRYING_OWED = "owed";
    public const CARRYING_PAID = "paid";

    public const CARRYING_LABELS = [
        self::CARRYING_OWED => "Dû",
        self::CARRYING_PAID => "Payé",
    ];

    public const STATUS_DRAFT = 'Brouillon';
    public const STATUS_TO_TREAT = 'A traiter';
    public const STATUS_SCHEDULED = "Planifiée";
    public const STATUS_SHIPPED = "Expédiée";

    public const CATEGORIE = 'expedition';
    public const NUMBER_PREFIX =  "DEX";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, unique: true, nullable: false)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::JSON)]
    private array $requesterPhoneNumbers = [];

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerOrderNumber = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $freeDelivery = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $compliantArticles = false;

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerName = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $customerRecipient = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $customerAddress = null;

    /**
     * "Date de prise en charge souhaitée"
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $requestCaredAt = null;

    /**
     * "Date d'enlèvement"
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $expectedPickedAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $plannedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $treatedAt = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $shipment = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $carrying = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $grossWeight = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $packCount = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class)]
    private ?Transporteur $carrier = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $validatedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $plannedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $treatedBy = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $requesters;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: ShippingRequestExpectedLine::class)]
    private Collection $expectedLines;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: ShippingRequestPack::class)]
    private Collection $packLines;

    #[ORM\OneToMany(mappedBy: 'shippingRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    #[ORM\OneToMany(mappedBy: 'shippingRequest', targetEntity: TrackingMovement::class)]
    private Collection $trackingMovements;

    #[ORM\OneToMany(mappedBy: 'shippingRequest', targetEntity: MouvementStock::class)]
    private Collection $stockMovements;

    public function __construct() {
        $this->requesters = new ArrayCollection();
        $this->expectedLines = new ArrayCollection();
        $this->lines = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->packLines = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->stockMovements = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRequesterPhoneNumbers(): array {
        return $this->requesterPhoneNumbers;
    }

    public function setRequesterPhoneNumbers(array $requesterPhoneNumbers): self {
        $this->requesterPhoneNumbers = $requesterPhoneNumbers;
        return $this;
    }

    public function getCustomerOrderNumber(): ?string {
        return $this->customerOrderNumber;
    }

    public function setCustomerOrderNumber(?string $customerOrderNumber): self {
        $this->customerOrderNumber = $customerOrderNumber;
        return $this;
    }

    public function isFreeDelivery(): bool {
        return $this->freeDelivery;
    }

    public function setFreeDelivery(bool $freeDelivery): self {
        $this->freeDelivery = $freeDelivery;
        return $this;
    }

    public function isCompliantArticles(): bool {
        return $this->compliantArticles;
    }

    public function setCompliantArticles(bool $compliantArticles): self {
        $this->compliantArticles = $compliantArticles;
        return $this;
    }

    public function getCustomerPhone(): ?string {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): self {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getCustomerName(): ?string {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): self {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerRecipient(): ?string {
        return $this->customerRecipient;
    }

    public function setCustomerRecipient(?string $customerRecipient): self {
        $this->customerRecipient = $customerRecipient;
        return $this;
    }

    public function getCustomerAddress(): ?string {
        return $this->customerAddress;
    }

    public function setCustomerAddress(?string $customerAddress): self {
        $this->customerAddress = $customerAddress;
        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getRequesters(): Collection {
        return $this->requesters;
    }

    public function setRequesters(?array $requesters): self {
        $this->requesters = new ArrayCollection($requesters);
        return $this;
    }

    public function getRequestCaredAt(): ?DateTime {
        return $this->requestCaredAt;
    }

    public function setRequestCaredAt(?DateTime $requestCaredAt): self {
        $this->requestCaredAt = $requestCaredAt;
        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        $this->carrier = $carrier;
        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        return $this;
    }

    public function getExpectedLines(): Collection {
        return $this->expectedLines;
    }

    public function getExpectedLine(ReferenceArticle $referenceArticle): ?ShippingRequestExpectedLine {
        return Stream::from($this->expectedLines)
            ->find(fn(ShippingRequestExpectedLine $line) => $line->getReferenceArticle() === $referenceArticle);
    }

    public function addExpectedLine(ShippingRequestExpectedLine $line): self {
        if (!$this->expectedLines->contains($line)) {
            $this->expectedLines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeExpectedLine(ShippingRequestExpectedLine $line): self {
        if ($this->expectedLines->removeElement($line)) {
            if ($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function getPackLines(): Collection {
        return $this->packLines;
    }

    public function addPackLine(ShippingRequestPack $packLine): self {
        if (!$this->packLines->contains($packLine)) {
            $this->packLines[] = $packLine;
            $packLine->setRequest($this);
        }

        return $this;
    }

    public function removePackLine(ShippingRequestPack $packLine): self {
        if ($this->packLines->removeElement($packLine)) {
            if ($packLine->getRequest() === $this) {
                $packLine->setRequest(null);
            }
        }

        return $this;
    }

    public function getValidatedAt(): ?DateTime {
        return $this->validatedAt;
    }

    public function setValidatedAt(?DateTime $validatedAt): self {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getPlannedAt(): ?DateTime {
        return $this->plannedAt;
    }

    public function setPlannedAt(?DateTime $plannedAt): self {
        $this->plannedAt = $plannedAt;
        return $this;
    }

    public function getTreatedAt(): ?DateTime {
        return $this->treatedAt;
    }

    public function setTreatedAt(?DateTime $treatedAt): self {
        $this->treatedAt = $treatedAt;
        return $this;
    }

    public function getValidatedBy(): ?Utilisateur {
        return $this->validatedBy;
    }

    public function setValidatedBy(?Utilisateur $validatedBy): self {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getPlannedBy(): ?Utilisateur {
        return $this->plannedBy;
    }

    public function setPlannedBy(?Utilisateur $plannedBy): self {
        $this->plannedBy = $plannedBy;
        return $this;
    }

    public function getTreatedBy(): ?Utilisateur {
        return $this->treatedBy;
    }

    public function setTreatedBy(?Utilisateur $treatedBy): self {
        $this->treatedBy = $treatedBy;
        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;
        return $this;
    }


    public function getStatusHistory(string $order = Criteria::ASC): Collection {
        return $this->statusHistory
            ->matching(Criteria::create()
                ->orderBy([
                    'date' => $order,
                    'id' => $order,
                ])
            );
    }

    public function addStatusHistory(StatusHistory $statusHistory): self
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setShippingRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getShippingRequest() === $this) {
                $statusHistory->setShippingRequest(null);
            }
        }

        return $this;
    }

    public function getShipment(): ?string {
        return $this->shipment;
    }

    public function setShipment(?string $shipment): self {
        if (isset($shipment) && !in_array($shipment, [self::SHIPMENT_NATIONAL, self::SHIPMENT_INTERNATIONAL])) {
            throw new RuntimeException('Invalid shipment value');
        }
        $this->shipment = $shipment;
        return $this;
    }

    public function getCarrying(): ?string {
        return $this->carrying;
    }

    public function setCarrying(?string $carrying): self {
        if (isset($carrying) && !in_array($carrying, [self::CARRYING_PAID, self::CARRYING_OWED])) {
            throw new RuntimeException('Invalid carrying value');
        }
        $this->carrying = $carrying;
        return $this;
    }

    public function getExpectedPickedAt(): ?DateTime {
        return $this->expectedPickedAt;
    }

    public function setExpectedPickedAt(?DateTime $expectedPickedAt): self {
        $this->expectedPickedAt = $expectedPickedAt;
        return $this;
    }

    public function getGrossWeight(): ?float {
        return $this->grossWeight;
    }

    public function setGrossWeight(?float $grossWeight): self {
        $this->grossWeight = $grossWeight;
        return $this;
    }

    public function getTrackingNumber(): ?string {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): self {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(?string $number): self {
        $this->number = $number;
        return $this;
    }

    public function getPackCount(): ?int {
        return $this->packCount;
    }

    public function setPackCount(?int $packCount): self {
        $this->packCount = $packCount;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if(!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setShippingRequest($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if($trackingMovement->getShippingRequest() === $this) {
                $trackingMovement->setShippingRequest(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getStockMovements(): Collection {
        return $this->stockMovements;
    }

    public function addStockMovement(MouvementStock $stockMovement): self {
        if(!$this->stockMovements->contains($stockMovement)) {
            $this->stockMovements[] = $stockMovement;
            $stockMovement->setShippingRequest($this);
        }

        return $this;
    }

    public function removeStockMovement(MouvementStock $stockMovement): self {
        if($this->stockMovements->contains($stockMovement)) {
            $this->stockMovements->removeElement($stockMovement);
            // set the owning side to null (unless already changed)
            if($stockMovement->getShippingRequest() === $this) {
                $stockMovement->setShippingRequest(null);
            }
        }

        return $this;
    }
}
