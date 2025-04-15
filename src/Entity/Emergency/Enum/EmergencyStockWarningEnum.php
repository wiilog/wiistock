<?php
namespace App\Entity\Emergency\Enum;


enum EmergencyStockWarningEnum: string {
    case SEND_MAIL_TO_REQUESTER = 'Demandeur';
    case SEND_MAIL_TO_BUYER = 'Acheteur';
}
