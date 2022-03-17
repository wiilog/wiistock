<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Repository\Transport\TransportCollectRequestNatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportCollectRequestNatureRepository::class)]
class TransportCollectRequestNature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Nature::class, inversedBy: 'transportCollectRequestNatures')]
    private ?Nature $nature = null;

    #[ORM\ManyToOne(targetEntity: TransportCollectRequest::class, inversedBy: 'transportCollectRequestNatures')]
    private ?TransportCollectRequest $transportCollectRequest = null;

    #[ORM\Column(type: 'integer')]
    private ?int $quantityToCollect = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $collectedQuantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        if($this->nature && $this->nature !== $nature) {
            $this->nature->removeTransportCollectRequestNature($this);
        }
        $this->nature = $nature;
        $nature?->addTransportCollectRequestNature($this);

        return $this;
    }

    public function getTransportCollectRequest(): ?TransportCollectRequest
    {
        return $this->transportCollectRequest;
    }

    public function setTransportCollectRequest(?TransportCollectRequest $transportCollectRequest): self {
        if($this->transportCollectRequest && $this->transportCollectRequest !== $transportCollectRequest) {
            $this->transportCollectRequest->removeTransportCollectRequestNature($this);
        }
        $this->transportCollectRequest = $transportCollectRequest;
        $transportCollectRequest?->addTransportCollectRequestNature($this);

        return $this;
    }

    public function getQuantityToCollect(): ?int
    {
        return $this->quantityToCollect;
    }

    public function setQuantityToCollect(int $quantityToCollect): self
    {
        $this->quantityToCollect = $quantityToCollect;

        return $this;
    }

    public function getCollectedQuantity(): ?int
    {
        return $this->collectedQuantity;
    }

    public function setCollectedQuantity(?int $collectedQuantity): self
    {
        $this->collectedQuantity = $collectedQuantity;

        return $this;
    }
}
