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

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $delay = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, enumType: TrackingEvent::class)]
    private ?TrackingEvent $trackingEvent;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pack $pack = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: Nature::class)]
    private ?Nature $nature = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): static
    {
        $this->pack = $pack;

        return $this;
    }

    public function getMovementDate(): ?DateTime
    {
        return $this->movementDate;
    }

    public function setMovementDate(DateTime $movementDate): static
    {
        $this->movementDate = $movementDate;

        return $this;
    }

    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function setDelay(int $delay): static
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
