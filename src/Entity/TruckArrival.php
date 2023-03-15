<?php

namespace App\Entity;

use App\Entity\Traits\AttachmentTrait;
use App\Repository\TruckArrivalRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TruckArrivalRepository::class)]
class TruckArrival
{
    use AttachmentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $number;

    #[ORM\OneToMany(mappedBy: 'truckArrival', targetEntity: TruckArrivalLine::class)]
    private Collection $trackingLines;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chauffeur $driver = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $creationDate = null;

    #[ORM\ManyToOne]
    private ?Emplacement $unloadingLocation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registrationNumber = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $operator = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Transporteur $carrier = null;

    #[ORM\OneToMany(mappedBy: 'truckArrival', targetEntity: Reserve::class)]
    private Collection $reserves;

    public function __construct()
    {
        $this->trackingLines = new ArrayCollection();
        $this->reserves = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return Collection<int, TruckArrivalLine>
     */
    public function getTrackingLines(): Collection
    {
        return $this->trackingLines;
    }

    public function addTrackingLine(TruckArrivalLine $trackingLine): self
    {
        if (!$this->trackingLines->contains($trackingLine)) {
            $this->trackingLines[] = $trackingLine;
            $trackingLine->setTruckArrival($this);
        }

        return $this;
    }

    public function removeTrackingLine(TruckArrivalLine $trackingLine): self
    {
        if ($this->trackingLines->removeElement($trackingLine)) {
            if ($trackingLine->getTruckArrival() === $this) {
                $trackingLine->setTruckArrival(null);
            }
        }

        return $this;
    }

    public function setTrackingLines(?iterable $trackingLines): self {
        foreach($this->getTrackingLines()->toArray() as $trackingLine) {
            $this->removeTrackingLine($trackingLine);
        }

        $this->trackingLines = new ArrayCollection();
        foreach($trackingLines ?? [] as $trackingLine) {
            $this->addTrackingLine($trackingLine);
        }

        return $this;
    }

    public function getDriver(): ?Chauffeur
    {
        return $this->driver;
    }

    public function setDriver(?Chauffeur $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function getCreationDate(): ?DateTime
    {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUnloadingLocation(): ?Emplacement
    {
        return $this->unloadingLocation;
    }

    public function setUnloadingLocation(?Emplacement $unloadingLocation): self
    {
        $this->unloadingLocation = $unloadingLocation;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getOperator(): ?Utilisateur
    {
        return $this->operator;
    }

    public function setOperator(?Utilisateur $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function getCarrier(): ?Transporteur
    {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self
    {
        $this->carrier = $carrier;

        return $this;
    }

    /**
     * @return Collection<int, Reserve>
     */
    public function getReserves(): Collection
    {
        return $this->reserves;
    }

    public function addReserve(Reserve $reserve): self
    {
        if (!$this->reserves->contains($reserve)) {
            $this->reserves[] = $reserve;
            $reserve->setTruckArrival($this);
        }

        return $this;
    }

    public function removeReserve(Reserve $reserve): self
    {
        if ($this->reserves->removeElement($reserve)) {
            if ($reserve->getTruckArrival() === $this) {
                $reserve->setTruckArrival(null);
            }
        }

        return $this;
    }

    public function setReserves(?iterable $reserves): self {
        foreach($this->getReserves()->toArray() as $reserve) {
            $this->removeReserve($reserve);
        }

        $this->reserves = new ArrayCollection();
        foreach($reserves ?? [] as $reserve) {
            $this->addReserve($reserve);
        }

        return $this;
    }
}
