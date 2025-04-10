<?php
namespace App\Entity\Emergency;


enum EndEmergencyCriteriaEnum: string {
    case MANUAL = 'manual';
    case REMAINING_QUANTITY = 'remaining_quantity';
    case END_DATE = 'end_date';


}
