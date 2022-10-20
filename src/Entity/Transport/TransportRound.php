<?php

namespace App\Entity\Transport;

use App\Entity\Emplacement;
use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportRoundRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: TransportRoundRepository::class)]
class TransportRound extends StatusHistoryContainer {

    public const NUMBER_PREFIX = 'T';

    public const STATUS_AWAITING_DELIVERER = 'En attente livreur';
    public const STATUS_ONGOING = 'En cours';
    public const STATUS_FINISHED = 'Terminée';

    public const STATUS_COLOR = [
        self::STATUS_AWAITING_DELIVERER => "preparing",
        self::STATUS_ONGOING => "ongoing",
        self::STATUS_FINISHED => "finished",
    ];

    public const STATUS_WORKFLOW_ROUND = [
        TransportRound::STATUS_AWAITING_DELIVERER,
        TransportRound::STATUS_ONGOING,
        TransportRound::STATUS_FINISHED,
    ];

    public const NAME_START_POINT = 'Départ tournée';
    public const NAME_START_POINT_SCHEDULE_CALCULATION = 'Départ calcul Horaire';
    public const NAME_END_POINT = 'Arrivée tournée';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\OneToMany(mappedBy: 'transportRound', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportRounds')]
    private ?Utilisateur $deliverer = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $beganAt = null;

    #[ORM\Column(type: 'json')]
    private ?array $coordinates = []; // ["startPoint" : [latitude: int , longitude: int ], "$startPointScheduleCalculation" : [latitude: int , longitude: int ], "endPoint" : [latitude: int , longitude: int ]]

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $startPoint = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $endPoint = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $startPointScheduleCalculation = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $estimatedDistance = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $realDistance = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $estimatedTime = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $noDeliveryToReturn = false;

    #[ORM\Column(type: 'boolean')]
    private ?bool $noCollectToReturn = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $roundUnderThresholdExceeded = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $roundUpperThresholdExceeded = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $rejectedOrderCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $rejectedPackCount = 0;

    #[ORM\OneToMany(mappedBy: 'transportRound', targetEntity: TransportRoundLine::class)]
    private Collection $transportRoundLines;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToMany(targetEntity: Emplacement::class)]
    private Collection $locations;

    public function __construct() {
        $this->transportRoundLines = new ArrayCollection();
        $this->locations = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpectedAt(): ?DateTime {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getEndedAt(): ?DateTime {
        return $this->endedAt;
    }

    public function setEndedAt(?DateTime $endedAt): self {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getDeliverer(): ?Utilisateur {
        return $this->deliverer;
    }

    public function setDeliverer(?Utilisateur $deliverer): self {
        if ($this->deliverer && $this->deliverer !== $deliverer) {
            $this->deliverer->removeTransportRound($this);
        }
        $this->deliverer = $deliverer;
        $deliverer?->addTransportRound($this);

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }


    public function setCreatedBy(Utilisateur $createdBy): self {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getBeganAt(): ?DateTime {
        return $this->beganAt;
    }

    public function setBeganAt(?DateTime $beganAt): self {
        $this->beganAt = $beganAt;

        return $this;
    }

    public function getStartPoint(): ?string {
        return $this->startPoint;
    }

    public function setStartPoint(?string $startPoint): self {
        $this->startPoint = $startPoint;

        return $this;
    }

    public function getEndPoint(): ?string {
        return $this->endPoint;
    }

    public function setEndPoint(?string $endPoint): self {
        $this->endPoint = $endPoint;

        return $this;
    }

    public function getStartPointScheduleCalculation(): ?string {
        return $this->startPointScheduleCalculation;
    }

    public function setStartPointScheduleCalculation(?string $startPointScheduleCalculation): self {
        $this->startPointScheduleCalculation = $startPointScheduleCalculation;

        return $this;
    }

    public function getEstimatedDistance(bool $convertToKm = true): ?float {
        return $this->estimatedDistance / ($convertToKm ? 1000 : 1);
    }

    public function setEstimatedDistance(?float $estimatedDistance): self {
        $this->estimatedDistance = $estimatedDistance;

        return $this;
    }

    public function hasNoDeliveryToReturn(): ?bool {
        return $this->noDeliveryToReturn;
    }

    public function setNoDeliveryToReturn(?bool $noDeliveryToReturn): self {
        $this->noDeliveryToReturn = $noDeliveryToReturn;
        return $this;
    }

    public function hasNoCollectToReturn(): ?bool {
        return $this->noCollectToReturn;
    }

    public function setNoCollectToReturn(?bool $noCollectToReturn): self {
        $this->noCollectToReturn = $noCollectToReturn;
        return $this;
    }

    public function getRealDistance(bool $convertToKm = true): ?float {
        return $this->realDistance / ($convertToKm ? 1000 : 1);
    }

    public function setRealDistance(?float $realDistance): self {
        $this->realDistance = $realDistance;

        return $this;
    }

    public function getEstimatedTime(): ?string {
        return $this->estimatedTime;
    }

    public function setEstimatedTime(?string $estimatedTime): self {
        $this->estimatedTime = $estimatedTime;

        return $this;
    }

    public function getTransportRoundLine(TransportOrder $transportOrder): ?TransportRoundLine {
        return $this->transportRoundLines
            ->filter(fn(TransportRoundLine $line) => $line->getOrder()?->getId() === $transportOrder->getId())
            ->first() ?: null;
    }

    /**
     * @return Collection<int, TransportRoundLine>
     */
    public function getTransportRoundLines(string $sortCriteria = 'priority'): Collection {
        $criteria = Criteria::create();
        return $this->transportRoundLines
            ->matching(
                $criteria
                    ->orderBy([
                        $sortCriteria => Criteria::ASC,
                    ])
            );
    }

    public function setTransportRoundLines(?array $lines): self {
        foreach ($this->getTransportRoundLines()->toArray() as $line) {
            $this->removeTransportRoundLine($line);
        }

        $this->transportRoundLines = new ArrayCollection();
        foreach ($lines as $line) {
            $this->addTransportRoundLine($line);
        }

        return $this;
    }

    public function addTransportRoundLine(TransportRoundLine $transportRoundLine): self {
        if (!$this->transportRoundLines->contains($transportRoundLine)) {
            $this->transportRoundLines[] = $transportRoundLine;
            $transportRoundLine->setTransportRound($this);
        }

        return $this;
    }

    public function removeTransportRoundLine(TransportRoundLine $transportRoundLine): self {
        if ($this->transportRoundLines->removeElement($transportRoundLine)) {
            // set the owning side to null (unless already changed)
            if ($transportRoundLine->getTransportRound() === $this) {
                $transportRoundLine->setTransportRound(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Emplacement>
     */
    public function getLocations(): Collection {
        return $this->locations;
    }

    public function setLocations(?array $lines): self {
        foreach ($this->getLocations()->toArray() as $line) {
            $this->removeLocation($line);
        }

        $this->locations = new ArrayCollection();
        foreach ($lines as $line) {
            $this->addLocation($line);
        }

        return $this;
    }

    public function addLocation(Emplacement $location): self {
        if (!$this->locations->contains($location)) {
            $this->locations[] = $location;
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self {
        $this->locations->removeElement($location);

        return $this;
    }

    public function getCoordinates(): array {
        return $this->coordinates ?? [];
    }

    public function setCoordinates(array $coordinates): self {
        $this->coordinates = $coordinates;
        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getStatusHistory(string $order = Criteria::ASC): Collection {
        return $this->statusHistory
            ->matching(Criteria::create()
                ->orderBy([
                    'date' => $order,
                    'id' => $order,
                ])
            );
    }

    public function addStatusHistory(StatusHistory $statusHistory): self {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setTransportRound($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getTransportRequest() === $this) {
                $statusHistory->setTransportRequest(null);
            }
        }

        return $this;
    }

    public function countRejectedPacks(): int {
        return Stream::from($this->getTransportRoundLines())->map(function(TransportRoundLine $line) {
            return $line->getOrder()->countRejectedPacks();
        })->sum();
    }

    public function getRejectedOrderCount(): int {
        return $this->rejectedOrderCount;
    }

    public function setRejectedOrderCount(int $rejectedOrderCount): self {
        $this->rejectedOrderCount = $rejectedOrderCount;
        return $this;
    }

    public function getRejectedPackCount(): int {
        return $this->rejectedPackCount;
    }

    public function setRejectedPackCount(int $rejectedPackCount): self {
        $this->rejectedPackCount = $rejectedPackCount;
        return $this;
    }

    public function getPairings(): array {
        $roundVehicle = $this->getVehicle();
        $roundsPairings = [];

        if ($roundVehicle) {
            foreach ($roundVehicle->getPairings() as $pairing) {
                $roundsPairings[] = $pairing;
            }

            foreach ($roundVehicle->getLocations() as $location) {
                foreach ($location->getPairings() as $pairing) {
                    $roundsPairings[] = $pairing;
                }
            }
        }

        foreach ($this->getTransportRoundLines() as $line) {
            $order = $line->getOrder();
            if (!$order->isRejected()) {
                foreach ($order->getPacks() as $orderPack) {
                    foreach ($orderPack->getPack()->getPairings() as $pairing) {
                        $roundsPairings[] = $pairing;
                    }
                }
            }
        }

        return $roundsPairings;
    }

    public function getVehicle(): ?Vehicle {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): TransportRound {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getCurrentOnGoingLine(): ?TransportRoundLine {
        return Stream::from($this->getTransportRoundLines())
            ->filter(fn(TransportRoundLine $line) => $line->getOrder()->getStatus()->getCode() === TransportOrder::STATUS_ONGOING)
            ->first();
    }

    /**
     * Is true if temperature ActionTrigger fired on round or on an order of this round
     */
    public function isThresholdExceeded(): bool {
        return $this->isRoundThresholdExceeded()
            || $this->isLineThresholdExceeded();
    }

    public function isUnderThresholdExceeded(): bool {
        return $this->isRoundUnderThresholdExceeded()
            || $this->isLineUnderThresholdExceeded();
    }

    public function isUpperThresholdExceeded(): bool {
        return $this->isRoundUpperThresholdExceeded()
            || $this->isLineUpperThresholdExceeded();
    }

    public function isLineThresholdExceeded(): bool {
        return Stream::from($this->getTransportRoundLines())
            ->some(fn(TransportRoundLine $line) => $line->getOrder()->isThresholdExceeded());
    }

    public function isLineUnderThresholdExceeded(): bool {
        return Stream::from($this->getTransportRoundLines())
            ->some(fn(TransportRoundLine $line) => $line->getOrder()->isUnderThresholdExceeded());
    }

    public function isLineUpperThresholdExceeded(): bool {
        return Stream::from($this->getTransportRoundLines())
            ->some(fn(TransportRoundLine $line) => $line->getOrder()->isUpperThresholdExceeded());
    }

    public function isRoundThresholdExceeded(): bool {
        return $this->isRoundUnderThresholdExceeded()
            || $this->isRoundUpperThresholdExceeded();
    }

    public function isRoundUnderThresholdExceeded(): bool {
        return $this->roundUnderThresholdExceeded;
    }

    public function setRoundUnderThresholdExceeded(bool $roundUnderThresholdExceeded): self {
        $this->roundUnderThresholdExceeded = $roundUnderThresholdExceeded;
        return $this;
    }

    public function isRoundUpperThresholdExceeded(): bool {
        return $this->roundUpperThresholdExceeded;
    }

    public function setRoundUpperThresholdExceeded(bool $roundUpperThresholdExceeded): self {
        $this->roundUpperThresholdExceeded = $roundUpperThresholdExceeded;
        return $this;
    }

}
