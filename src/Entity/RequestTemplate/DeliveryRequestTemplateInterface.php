<?php

namespace App\Entity\RequestTemplate;

use Doctrine\Common\Collections\Collection;

interface DeliveryRequestTemplateInterface {
    public const DELIVERY_REQUEST_TEMPLATE_USAGES = [
        DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION->value => "Actionneur",
        DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK->value => "Stock dormant",
    ];

    public function getUsage(): DeliveryRequestTemplateUsageEnum;

    /**
     * @return Collection<RequestTemplateLine>
     */
    public function getLines(): Collection;

    public function setLines(Collection $lines): self;
}
