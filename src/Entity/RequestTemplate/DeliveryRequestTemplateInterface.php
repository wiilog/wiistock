<?php

namespace App\Entity\RequestTemplate;

interface DeliveryRequestTemplateInterface {
    public function getUsage(): DeliveryRequestTemplateUsageEnum;
}
