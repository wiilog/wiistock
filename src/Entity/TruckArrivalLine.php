<?php

namespace App\Entity;

use App\Entity\Traits\AttachmentTrait;
use App\Repository\TruckArrivalLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TruckArrivalLineRepository::class)]
class TruckArrivalLine
{
    use AttachmentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private ?string $number = null;

    #[ORM\ManyToMany(targetEntity: Arrivage::class, inversedBy: 'truckArrivalLines')]
    private Collection $arrival;

    #[ORM\OneToOne(mappedBy: 'line', cascade: ['persist', 'remove'])]
    private ?Reserve $reserve = null;

    #[ORM\ManyToOne(inversedBy: 'trackingLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TruckArrival $truckArrival = null;

    public function __construct()
    {
        $this->arrival = new ArrayCollection();
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

    /**
     * @return Collection<int, Arrivage>
     */
    public function getArrival(): Collection
    {
        return $this->arrival;
    }

    public function addArrival(Arrivage $arrival): self
    {
        if (!$this->arrival->contains($arrival)) {
            $this->arrival->add($arrival);
        }

        return $this;
    }

    public function removeArrival(Arrivage $arrival): self
    {
        $this->arrival->removeElement($arrival);

        return $this;
    }

    public function getReserve(): ?Reserve
    {
        return $this->reserve;
    }

    public function setReserve(?Reserve $reserve): self
    {
        if ($reserve === null && $this->reserve !== null) {
            $this->reserve->setLine(null);
        }

        if ($reserve !== null && $reserve->getLine() !== $this) {
            $reserve->setLine($this);
        }

        $this->reserve = $reserve;

        return $this;
    }

    public function getTruckArrival(): ?TruckArrival
    {
        return $this->truckArrival;
    }

    public function setTruckArrival(?TruckArrival $truckArrival): self
    {
        $this->truckArrival = $truckArrival;

        return $this;
    }
}
