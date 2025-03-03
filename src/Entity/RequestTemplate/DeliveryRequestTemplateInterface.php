<?php

namespace App\Entity\RequestTemplate;

interface DeliveryRequestTemplateInterface {
    public function getUsage(): DeliveryRequestTemplateUsageEnum;

    public const DELIVERY_REQUEST_TEMPLATE_USAGES = [
        DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION->value => "Actionneur",
        DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK->value => "Stock dormant",
    ];
}
