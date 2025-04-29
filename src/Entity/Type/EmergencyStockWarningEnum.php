<?php
namespace App\Entity\Type;


enum EmergencyStockWarningEnum: string {
    case SEND_MAIL_TO_REQUESTER = 'send_mail_to_requester';
    case SEND_MAIL_TO_BUYER = 'send_mail_to_buyer';

    public static function label(string $value): string
    {
        return match($value) {
            self::SEND_MAIL_TO_BUYER->value => "Acheteur",
            self::SEND_MAIL_TO_REQUESTER->value => "Demandeur",
        };
    }
}
