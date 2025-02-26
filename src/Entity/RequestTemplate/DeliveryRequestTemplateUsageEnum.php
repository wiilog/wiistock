<?php

namespace App\Entity\RequestTemplate;

enum DeliveryRequestTemplateUsageEnum: string {
    case TRIGGER_ACTION = "TriggerAction";
    case SLEEPING_STOCK = "SleepingStock";
}
