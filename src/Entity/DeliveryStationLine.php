<?php

namespace App\Entity;

use App\Repository\DeliveryStationLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryStationLineRepository::class)]
class DeliveryStationLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $deliveryType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?VisibilityGroup $visibilityGroup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $destinationLocation = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $receiver = null;

    #[ORM\Column(nullable: true)]
    private array $filters = [];

    #[ORM\Column(type: Types::TEXT)]
    private ?string $welcomeMessage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryType(): ?Type
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(?Type $deliveryType): self
    {
        $this->deliveryType = $deliveryType;

        return $this;
    }

    public function getVisibilityGroup(): ?VisibilityGroup
    {
        return $this->visibilityGroup;
    }

    public function setVisibilityGroup(?VisibilityGroup $visibilityGroup): self
    {
        $this->visibilityGroup = $visibilityGroup;

        return $this;
    }

    public function getDestinationLocation(): ?Emplacement
    {
        return $this->destinationLocation;
    }

    public function setDestinationLocation(?Emplacement $destinationLocation): self
    {
        $this->destinationLocation = $destinationLocation;

        return $this;
    }

    public function getReceiver(): ?Utilisateur
    {
        return $this->receiver;
    }

    public function setReceiver(?Utilisateur $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function getWelcomeMessage(): ?string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(string $welcomeMessage): self
    {
        $this->welcomeMessage = $welcomeMessage;

        return $this;
    }
}
