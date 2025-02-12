<?php

namespace App\Entity\IOT;

enum DeliveryRequestTemplateTypeEnum: string {
    case TRIGGER_ACTION = "TriggerAction";
    case SLEEPING_STOCK = "SleepingStock";
}
