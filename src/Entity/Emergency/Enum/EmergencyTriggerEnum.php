<?php
namespace App\Entity\Emergency\Enum;


enum EmergencyTriggerEnum: string {
    case SUPPLIER = 'supplier';
    case REFERENCE = 'reference';
}
