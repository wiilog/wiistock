<?php

namespace App\Entity\Tracking;

use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Repository\Tracking\TrackingDelayRecordRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingDelayRecordRepository::class)]
class TrackingDelayRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $movementDate = null;

    /**
     * The column contains the delay between the T0 and the movement date
     */
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $delay = null;

    /**
     * Is null if the movement from is a unpause movement
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true, enumType: TrackingEvent::class)]
    private ?TrackingEvent $trackingEvent;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pack $pack = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: Nature::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Nature $nature = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;

        return $this;
    }

    public function getMovementDate(): ?DateTime
    {
        return $this->movementDate;
    }

    public function setMovementDate(DateTime $movementDate): self
    {
        $this->movementDate = $movementDate;

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function setDelay(int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function getTrackingEvent(): ?TrackingEvent {
        return $this->trackingEvent;
    }

    public function setTrackingEvent(?TrackingEvent $trackingEvent): self {
        $this->trackingEvent = $trackingEvent;
        return $this;
    }
}
