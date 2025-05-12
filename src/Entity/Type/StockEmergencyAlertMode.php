<?php

namespace App\Entity\Type;

enum StockEmergencyAlertMode: string {
    case SEND_MAIL_TO_REQUESTER = 'send_mail_to_requester';
    case SEND_MAIL_TO_BUYER = 'send_mail_to_buyer';
}
