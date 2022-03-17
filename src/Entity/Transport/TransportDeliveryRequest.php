<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportDeliveryRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportDeliveryRequestRepository::class)]
class TransportDeliveryRequest extends TransportRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\OneToMany(mappedBy: 'transportDeliveryRequest', targetEntity: TransportDeliveryRequestNature::class)]
    private Collection $transportDeliveryRequestNatures;

    #[ORM\ManyToOne(targetEntity: TransportRound::class, inversedBy: 'transportDeliveryRequests')]
    private ?TransportRound $transportRound = null;

    public function __construct()
    {
        parent::__construct();
        $this->transportDeliveryRequestNatures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpectedAt(): ?DateTime
    {
        return $this->expectedAt;
    }

    public function setExpectedAt(?DateTime $expectedAt): self
    {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getEmergency(): ?string
    {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self
    {
        $this->emergency = $emergency;

        return $this;
    }

    /**
     * @return Collection<int, TransportDeliveryRequestNature>
     */
    public function getTransportDeliveryRequestNatures(): Collection
    {
        return $this->transportDeliveryRequestNatures;
    }

    public function addTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if (!$this->transportDeliveryRequestNatures->contains($transportDeliveryRequestNature)) {
            $this->transportDeliveryRequestNatures[] = $transportDeliveryRequestNature;
            $transportDeliveryRequestNature->setTransportDeliveryRequest($this);
        }

        return $this;
    }

    public function removeTransportDeliveryRequestNature(TransportDeliveryRequestNature $transportDeliveryRequestNature): self
    {
        if ($this->transportDeliveryRequestNatures->removeElement($transportDeliveryRequestNature)) {
            // set the owning side to null (unless already changed)
            if ($transportDeliveryRequestNature->getTransportDeliveryRequest() === $this) {
                $transportDeliveryRequestNature->setTransportDeliveryRequest(null);
            }
        }

        return $this;
    }

    public function getTransportRound(): ?TransportRound
    {
        return $this->transportRound;
    }

    public function setTransportRound(?TransportRound $transportRound): self {
        if($this->transportRound && $this->transportRound !== $transportRound) {
            $this->transportRound->removeTransportDeliveryRequest($this);
        }
        $this->transportRound = $transportRound;
        $transportRound?->addTransportDeliveryRequest($this);

        return $this;
    }
}
