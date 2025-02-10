<?php

namespace App\Entity\Tracking;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Repository\Tracking\TrackingDelayRecordRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Like a trackingMovement copy
 */
#[ORM\Entity(repositoryClass: TrackingDelayRecordRepository::class)]
#[ORM\Index(fields: ["date"], name: "IDX_WIILOG_DATE")]
class TrackingDelayRecord {

    public const TYPE_ARRIVAL = 'Arrivage';
    public const TYPE_TRUCK_ARRIVAL = 'Arrivage camion';


    /**
     * @var bool|null Attribute not saved in database, we don't want it in tracking delay record table.
     * It is used to calculate tracking delay.
     */
    private ?bool $now = false;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $date = null;

    /**
     * The column contains the remaining time: the nature tracking delay less the pack elapsed time
     */
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $remainingTrackingDelay = null;

    /**
     * null for unpause event
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: TrackingEvent::class)]
    private ?TrackingEvent $trackingEvent;

    #[ORM\ManyToOne(targetEntity: TrackingDelay::class, inversedBy: "records")]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrackingDelay $trackingDelay = null;

    /**
     * @var Statut|null Copy of tracking movement's type if record is based on tracking movement
     * Or if it's not then it's linked to TrackingDelayRecord::TYPE_ARRIVAL or TrackingDelayRecord::TYPE_TRUCK_ARRIVAL
     */
    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Statut $type = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: Nature::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Nature $newNature = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;
        return $this;
    }

    public function getNewNature(): ?Nature {
        return $this->newNature;
    }

    public function setNewNature(?Nature $newNature): self {
        $this->newNature = $newNature;
        return $this;
    }

    public function getRemainingTrackingDelay(): ?int {
        return $this->remainingTrackingDelay;
    }

    public function setRemainingTrackingDelay(int $remainingTrackingDelay): self {
        $this->remainingTrackingDelay = $remainingTrackingDelay;
        return $this;
    }

    public function getTrackingEvent(): ?TrackingEvent {
        return $this->trackingEvent;
    }

    public function setTrackingEvent(?TrackingEvent $trackingEvent): self {
        $this->trackingEvent = $trackingEvent;
        return $this;
    }

    public function getType(): ?Statut {
        return $this->type;
    }

    public function setType(?Statut $type): self {
        $this->type = $type;
        return $this;
    }

    public function setNow(bool $now): self {
        $this->now = $now;
        return $this;
    }

    public function isNow(): bool {
        return $this->now;
    }

    public function getTrackingDelay(): ?TrackingDelay {
        return $this->trackingDelay;
    }

    public function setTrackingDelay(?TrackingDelay $trackingDelay): self {
        if ($this->trackingDelay && $this->trackingDelay !== $trackingDelay) {
            $this->trackingDelay->removeRecord($this);
        }
        $this->trackingDelay = $trackingDelay;
        $trackingDelay?->addRecord($this);

        return $this;
    }
}
