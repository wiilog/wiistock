<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportRoundLineRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRoundLineRepository::class)]
class TransportRoundLine {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TransportOrder::class, inversedBy: 'transportRoundLines')]
    private ?TransportOrder $order = null;

    #[ORM\ManyToOne(targetEntity: TransportRound::class, inversedBy: 'transportRoundLines')]
    private ?TransportRound $transportRound = null;

    #[ORM\Column(type: 'integer')]
    private ?int $priority = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $estimatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $fulfilledAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $cancelledAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $rejectedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $failedAt = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrder(): ?TransportOrder {
        return $this->order;
    }

    public function setOrder(?TransportOrder $order): self {
        if ($this->order && $this->order !== $order) {
            $this->order->removeTransportRoundLine($this);
        }
        $this->order = $order;
        $order?->addTransportRoundLine($this);

        return $this;
    }

    public function getTransportRound(): ?TransportRound {
        return $this->transportRound;
    }

    public function setTransportRound(?TransportRound $transportRound): self {
        if ($this->transportRound && $this->transportRound !== $transportRound) {
            $this->transportRound->removeTransportRoundLine($this);
        }
        $this->transportRound = $transportRound;
        $transportRound?->addTransportRoundLine($this);

        return $this;
    }

    public function getPriority(): ?int {
        return $this->priority;
    }

    public function setPriority(int $priority): self {
        $this->priority = $priority;

        return $this;
    }

    public function getEstimatedAt(): ?DateTime {
        return $this->estimatedAt;
    }

    public function setEstimatedAt(?DateTime $estimatedAt): self {
        $this->estimatedAt = $estimatedAt;

        return $this;
    }

    public function getFulfilledAt(): ?DateTime {
        return $this->fulfilledAt;
    }

    public function setFulfilledAt(?DateTime $fulfilledAt): self {
        $this->fulfilledAt = $fulfilledAt;

        return $this;
    }

    public function getCancelledAt(): ?DateTime {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?DateTime $cancelledAt): void {
        $this->cancelledAt = $cancelledAt;
    }

    public function getRejectedAt(): ?DateTime {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?DateTime $rejectedAt): self {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }

    public function getFailedAt(): ?DateTime {
        return $this->failedAt;
    }

    public function setFailedAt(?DateTime $failedAt): self {
        $this->failedAt = $failedAt;

        return $this;
    }

}
