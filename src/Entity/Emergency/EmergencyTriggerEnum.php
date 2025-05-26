<?php
namespace App\Entity\Emergency;


enum EmergencyTriggerEnum: string {
    case SUPPLIER = 'supplier';
    case REFERENCE = 'reference';
}
