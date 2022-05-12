<?php

namespace App\Entity\Transport;

use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\Transport\TransportRoundRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRoundRepository::class)]
class TransportRound
{
    public const NUMBER_PREFIX = 'T';

    public const STATUS_AWAITING_DELIVERER = 'En attente livreur';
    public const STATUS_ONGOING = 'En cours';
    public const STATUS_FINISHED = 'Terminée';

    public const STATUS_COLOR = [
        self::STATUS_AWAITING_DELIVERER => "preparing",
        self::STATUS_ONGOING => "ongoing",
        self::STATUS_FINISHED => "finished",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'transportRounds')]
    private ?Utilisateur $deliverer = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $beganAt = null;

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

    #[ORM\OneToMany(mappedBy: 'transportRound', targetEntity: TransportRoundLine::class)]
    private Collection $transportRoundLines;

    public function __construct()
    {
        $this->transportRoundLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpectedAt(): ?DateTime
    {
        return $this->expectedAt;
    }

    public function setExpectedAt(DateTime $expectedAt): self
    {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getEndedAt(): ?DateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(?DateTime $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getDeliverer(): ?Utilisateur
    {
        return $this->deliverer;
    }

    public function setDeliverer(?Utilisateur $deliverer): self {
        if($this->deliverer && $this->deliverer !== $deliverer) {
            $this->deliverer->removeTransportRound($this);
        }
        $this->deliverer = $deliverer;
        $deliverer?->addTransportRound($this);

        return $this;
    }

    public function getBeganAt(): ?DateTime
    {
        return $this->beganAt;
    }

    public function setBeganAt(?DateTime $beganAt): self
    {
        $this->beganAt = $beganAt;

        return $this;
    }

    public function getStartPoint(): ?string
    {
        return $this->startPoint;
    }

    public function setStartPoint(?string $startPoint): self
    {
        $this->startPoint = $startPoint;

        return $this;
    }

    public function getEndPoint(): ?string
    {
        return $this->endPoint;
    }

    public function setEndPoint(?string $endPoint): self
    {
        $this->endPoint = $endPoint;

        return $this;
    }

    public function getStartPointScheduleCalculation(): ?string
    {
        return $this->startPointScheduleCalculation;
    }

    public function setStartPointScheduleCalculation(?string $startPointScheduleCalculation): self
    {
        $this->startPointScheduleCalculation = $startPointScheduleCalculation;

        return $this;
    }

    public function getEstimatedDistance(): ?float
    {
        return $this->estimatedDistance;
    }

    public function setEstimatedDistance(?float $estimatedDistance): self
    {
        $this->estimatedDistance = $estimatedDistance;

        return $this;
    }

    public function getRealDistance(): ?float
    {
        return $this->realDistance;
    }

    public function setRealDistance(?float $realDistance): self
    {
        $this->realDistance = $realDistance;

        return $this;
    }

    public function getEstimatedTime(): ?string
    {
        return $this->estimatedTime;
    }

    public function setEstimatedTime(?string $estimatedTime): self
    {
        $this->estimatedTime = $estimatedTime;

        return $this;
    }

    /**
     * @return Collection<int, TransportRoundLine>
     */
    public function getTransportRoundLines(): Collection
    {
        return $this->transportRoundLines;
    }

    public function addTransportRoundLine(TransportRoundLine $transportRoundLine): self
    {
        if (!$this->transportRoundLines->contains($transportRoundLine)) {
            $this->transportRoundLines[] = $transportRoundLine;
            $transportRoundLine->setTransportRound($this);
        }

        return $this;
    }

    public function removeTransportRoundLine(TransportRoundLine $transportRoundLine): self
    {
        if ($this->transportRoundLines->removeElement($transportRoundLine)) {
            // set the owning side to null (unless already changed)
            if ($transportRoundLine->getTransportRound() === $this) {
                $transportRoundLine->setTransportRound(null);
            }
        }

        return $this;
    }

}
