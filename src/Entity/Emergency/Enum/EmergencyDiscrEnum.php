<?php
namespace App\Entity\Emergency\Enum;


enum EmergencyDiscrEnum: string {
    case STOCK_EMERGENCY = 'stock_emergency';
    case TRACKING_EMERGENCY = 'tracking_emergency';
}
