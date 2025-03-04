<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\DeliveryRequestTemplateRepositoryTriggerAction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateRepositoryTriggerAction::class)]
class DeliveryRequestTemplateSleepingStock extends RequestTemplate implements DeliveryRequestTemplateInterface {

    use DeliveryRequestTemplateTrait;

    #[ORM\OneToOne(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private ?Attachment $buttonIcon = null;

    public function __construct() {
        parent::__construct();
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
