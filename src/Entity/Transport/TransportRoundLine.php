<?php

namespace App\Entity\Transport;

use App\Repository\TransportRoundLineRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRoundLineRepository::class)]
class TransportRoundLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'transportRoundLines')]
    private ?TransportOrder $transportOrder = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class, inversedBy: 'transportRoundLines')]
    private ?TransportRound $transportRound = null;

    #[ORM\Column(type: 'integer')]
    private ?int $priority = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $estimatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $fullfilledAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $rejectedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransportOrder(): ?TransportOrder
    {
        return $this->transportOrder;
    }

    public function setTransportOrder(?TransportOrder $transportOrder): self {
        if($this->transportOrder && $this->transportOrder !== $transportOrder) {
            $this->transportOrder->removeTransportRoundLine($this);
        }
        $this->transportOrder = $transportOrder;
        $transportOrder?->addTransportRoundLine($this);

        return $this;
    }

    public function getTransportRound(): ?TransportRound
    {
        return $this->transportRound;
    }

    public function setTransportRound(?TransportRound $transportRound): self {
        if($this->transportRound && $this->transportRound !== $transportRound) {
            $this->transportRound->removeTransportRoundLine($this);
        }
        $this->transportRound = $transportRound;
        $transportRound?->addTransportRoundLine($this);

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getEstimatedAt(): ?DateTime
    {
        return $this->estimatedAt;
    }

    public function setEstimatedAt(?DateTime $estimatedAt): self
    {
        $this->estimatedAt = $estimatedAt;

        return $this;
    }

    public function getFullfilledAt(): ?DateTime
    {
        return $this->fullfilledAt;
    }

    public function setFullfilledAt(?DateTime $fullfilledAt): self
    {
        $this->fullfilledAt = $fullfilledAt;

        return $this;
    }

    public function getRejectedAt(): ?DateTime
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?DateTime $rejectedAt): self
    {
        $this->rejectedAt = $rejectedAt;

        return $this;
    }
}
