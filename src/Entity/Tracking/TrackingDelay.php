<?php

namespace App\Entity\Tracking;

use App\Repository\Tracking\TrackingDelayRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingDelayRepository::class)]
class TrackingDelay {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * @var int|null Elapsed time of the pack in second
     */
    #[ORM\Column(type: Types::BIGINT, nullable: false)]
    private ?int $elapsedTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: TrackingEvent::class)]
    private ?TrackingEvent $lastTrackingEvent;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $calculatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $limitTreatmentDate = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private ?bool $stoppedByMovementEdit = false;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Pack $pack = null;

    /**
     * @var Collection<int, TrackingDelayRecord>
     */
    #[ORM\OneToMany(mappedBy: "trackingDelay", targetEntity: TrackingDelayRecord::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $records;

    public function __construct() {
        $this->records = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getElapsedTime(): ?int {
        return $this->elapsedTime;
    }

    public function setElapsedTime(?int $elapsedTime): self {
        $this->elapsedTime = $elapsedTime;
        return $this;
    }

    public function getCalculatedAt(): ?DateTime {
        return $this->calculatedAt;
    }

    public function setCalculatedAt(?DateTime $calculatedAt): self {
        $this->calculatedAt = $calculatedAt;
        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;

        return $this;
    }

    public function isTimerPaused(): bool {
        return (
            $this->lastTrackingEvent === TrackingEvent::PAUSE
        );
    }

    public function getLastTrackingEvent(): ?TrackingEvent {
        return $this->lastTrackingEvent;
    }

    public function setLastTrackingEvent(?TrackingEvent $lastTrackingEvent): self {
        $this->lastTrackingEvent = $lastTrackingEvent;
        return $this;
    }

    public function getLimitTreatmentDate(): ?DateTime {
        return $this->limitTreatmentDate;
    }

    public function setLimitTreatmentDate(?DateTime $limitTreatmentDate): self {
        $this->limitTreatmentDate = $limitTreatmentDate;

        return $this;
    }

    public function isStoppedByMovementEdit(): ?bool {
        return $this->stoppedByMovementEdit;
    }

    public function setStoppedByMovementEdit(?bool $stoppedByMovementEdit): self {
        $this->stoppedByMovementEdit = $stoppedByMovementEdit;
        return $this;
    }

    /**
     * @return Collection<int, TrackingDelayRecord>
     */
    public function getRecords(): Collection {
        return $this->records;
    }

    public function addRecord(TrackingDelayRecord $record): self {
        if (!$this->records->contains($record)) {
            $this->records[] = $record;
            $record->setTrackingDelay($this);
        }

        return $this;
    }

    public function removeRecord(TrackingDelayRecord $record): self {
        if ($this->records->removeElement($record)) {
            if ($record->getTrackingDelay() === $this) {
                $record->setTrackingDelay(null);
            }
        }

        return $this;
    }

    /**
     * @param iterable<TrackingDelayRecord> $records
     */
    public function setRecords(iterable $records): self {
        foreach($this->getRecords()->toArray() as $record) {
            $this->removeRecord($record);
        }

        $this->records = new ArrayCollection();
        $this->addRecords($records);

        return $this;
    }

    /**
     * @param iterable<TrackingDelayRecord> $records
     */
    public function addRecords(iterable $records): self {
        foreach($records as $record) {
            $this->addRecord($record);
        }

        return $this;
    }
}
