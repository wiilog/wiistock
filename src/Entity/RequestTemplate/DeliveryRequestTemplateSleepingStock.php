<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Repository\RequestTemplate\DeliveryRequestTemplateSleepingStockRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateSleepingStockRepository::class)]
class DeliveryRequestTemplateSleepingStock extends RequestTemplate implements DeliveryRequestTemplateInterface {

    use DeliveryRequestTemplateTrait;

    /**
     * @var Collection<RequestTemplateLine>
     */
    private Collection $lines;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    public function __construct() {
        parent::__construct();
    }

    /**
     * @return Collection<RequestTemplateLine>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function getButtonIcon(): ?Attachment {
        return $this->buttonIcon;
    }

    public function setButtonIcon(?Attachment $buttonIcon): self {
        $this->buttonIcon = $buttonIcon;

        return $this;
    }

    public function getUsage(): DeliveryRequestTemplateUsageEnum {
        return DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK;
    }
}
