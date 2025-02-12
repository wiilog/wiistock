<?php

namespace App\Entity\RequestTemplate;

enum DeliveryRequestTemplateTypeEnum: string {
    case TRIGGER_ACTION = "TriggerAction";
    case SLEEPING_STOCK = "SleepingStock";
}
