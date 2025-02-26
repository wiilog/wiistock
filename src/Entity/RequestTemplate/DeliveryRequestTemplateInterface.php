<?php

namespace App\Entity\RequestTemplate;

interface DeliveryRequestTemplateInterface {
    public function getUsage(): DeliveryRequestTemplateUsageEnum;

    const DELIVERY_REQUEST_TEMPLATE_USAGE = [
        DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION->value => "Actionneur",
        DeliveryRequestTemplateUsageEnum::SLEEPING_STOCK->value => "Stock dormant",
    ];

}
