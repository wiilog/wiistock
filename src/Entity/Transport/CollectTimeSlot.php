<?php

namespace App\Entity\Transport;

use App\Repository\Transport\CollectTimeSlotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CollectTimeSlotRepository::class)]
class CollectTimeSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $start;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $end = null;

    #[ORM\OneToMany(mappedBy: 'timeSlot', targetEntity: TransportCollectRequest::class)]
    private Collection $transportCollectRequests;

    public function __construct()
    {
        $this->transportCollectRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStart(): ?string
    {
        return $this->start;
    }

    public function setStart(string $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?string
    {
        return $this->end;
    }

    public function setEnd(string $end): self
    {
        $this->end = $end;

        return $this;
    }

    /**
     * @return Collection<int, TransportCollectRequest>
     */
    public function getTransportCollectRequests(): Collection
    {
        return $this->transportCollectRequests;
    }

    public function addTransportCollectRequest(TransportCollectRequest $transportCollectRequest): self
    {
        if (!$this->transportCollectRequests->contains($transportCollectRequest)) {
            $this->transportCollectRequests[] = $transportCollectRequest;
            $transportCollectRequest->setTimeSlot($this);
        }

        return $this;
    }

    public function removeTransportCollectRequest(TransportCollectRequest $transportCollectRequest): self
    {
        if ($this->transportCollectRequests->removeElement($transportCollectRequest)) {
            // set the owning side to null (unless already changed)
            if ($transportCollectRequest->getTimeSlot() === $this) {
                $transportCollectRequest->setTimeSlot(null);
            }
        }

        return $this;
    }
}
