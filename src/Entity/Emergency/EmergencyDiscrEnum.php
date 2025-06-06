<?php
namespace App\Entity\Emergency;


enum EmergencyDiscrEnum: string {
    case STOCK_EMERGENCY = 'stock_emergency';
    case TRACKING_EMERGENCY = 'tracking_emergency';
}
