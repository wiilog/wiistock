<?php

namespace App\Entity;

use App\Entity\RequestTemplate\DeliveryRequestTemplateSleepingStock;
use App\Repository\SleepingStockRequestInformationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SleepingStockRequestInformationRepository::class)]
class SleepingStockRequestInformation {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $buttonActionLabel = null;

    #[ORM\ManyToOne(targetEntity: DeliveryRequestTemplateSleepingStock::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'cascade')]
    private ?DeliveryRequestTemplateSleepingStock $deliveryRequestTemplate = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getButtonActionLabel(): ?string {
        return $this->buttonActionLabel;
    }

    public function setButtonActionLabel(string $buttonActionLabel): self {
        $this->buttonActionLabel = $buttonActionLabel;

        return $this;
    }

    public function getDeliveryRequestTemplate(): ?DeliveryRequestTemplateSleepingStock
    {
        return $this->deliveryRequestTemplate;
    }

    public function setDeliveryRequestTemplate(?DeliveryRequestTemplateSleepingStock $deliveryRequestTemplate): self
    {
        $this->deliveryRequestTemplate = $deliveryRequestTemplate;

        return $this;
    }
}
