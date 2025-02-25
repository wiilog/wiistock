<?php

namespace App\Entity;

use App\Entity\RequestTemplate\DeliveryRequestTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTypeEnum;
use App\Repository\SleepingStockRequestInformationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SleepingStockRequestInformationRepository::class)]
class SleepingStockRequestInformation {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $buttonActionLabel = null;


    /**
     * Only DeliveryRequestTemplate with type DeliveryRequestTemplateTypeEnum::SLEEPING_STOCK are allowed.
     */
    #[ORM\ManyToOne(targetEntity: DeliveryRequestTemplate::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?DeliveryRequestTemplate $DeliveryRequestTemplate = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function validateDeliveryRequestTemplate(): void {
        if ($this->DeliveryRequestTemplate
            && $this->DeliveryRequestTemplate->getDeliveryRequestTemplateType() !== DeliveryRequestTemplateTypeEnum::SLEEPING_STOCK) {
            throw new \InvalidArgumentException('Only DeliveryRequestTemplate with type SLEEPING_STOCK are allowed');
        }
    }

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

    public function getDeliveryRequestTemplate(): ?DeliveryRequestTemplate {
        return $this->DeliveryRequestTemplate;
    }

    public function setDeliveryRequestTemplate(?DeliveryRequestTemplate $DeliveryRequestTemplate): self {
        $this->DeliveryRequestTemplate = $DeliveryRequestTemplate;

        return $this;
    }
}
