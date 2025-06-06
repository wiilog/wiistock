<?php

namespace App\Entity\RequestTemplate;

use App\Controller\SleepingStock\IndexController;
use App\Entity\Attachment;
use App\Repository\RequestTemplate\DeliveryRequestTemplateSleepingStockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateSleepingStockRepository::class)]
class DeliveryRequestTemplateSleepingStock extends RequestTemplate implements DeliveryRequestTemplateInterface {

    use DeliveryRequestTemplateTrait;

    /**
     * Not managed by the ORM, because we dont know the lines in advance
     * the line corresponding to the choice of the user in the sleeping stock form
     * @see IndexController::submit()
     * @var Collection<int, RequestTemplateLine>
     */
    private Collection $lines;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    /**
     * @return Collection<int, RequestTemplateLineReference>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function setLines(Collection $lines): self {
        $this->lines = $lines;

        return $this;
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
