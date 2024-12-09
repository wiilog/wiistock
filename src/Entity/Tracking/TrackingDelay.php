<?php

namespace App\Entity\Tracking;

use App\Repository\Tracking\TrackingDelayRepository;
use DateTime;
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

    #[ORM\OneToOne(inversedBy: "trackingDelay", targetEntity: Pack::class)]
    private ?Pack $pack = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $limitTreatmentDate = null;

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
        if($this->pack && $this->pack->getTrackingDelay() !== $this) {
            $oldPack = $this->pack;
            $this->pack = null;
            $oldPack->setTrackingDelay(null);
        }
        $this->pack = $pack;
        if($this->pack && $this->pack->getTrackingDelay() !== $this) {
            $this->pack->setTrackingDelay($this);
        }

        return $this;
    }

    public function isTimerStopped(): bool {
        return (
            $this->lastTrackingEvent === TrackingEvent::PAUSE
            || $this->lastTrackingEvent === TrackingEvent::STOP
        );
    }

    public function getLastTrackingEvent(): ?TrackingEvent {
        return $this->lastTrackingEvent;
    }

    public function setLastTrackingEvent(?TrackingEvent $lastTrackingEvent): self {
        $this->lastTrackingEvent = $lastTrackingEvent;
        return $this;
    }

    public function getLimitTreatmentDate(): ?DateTime
    {
        return $this->limitTreatmentDate;
    }

    public function setLimitTreatmentDate(?DateTime $limitTreatmentDate): self
    {
        $this->limitTreatmentDate = $limitTreatmentDate;

        return $this;
    }
}
