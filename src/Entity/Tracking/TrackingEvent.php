<?php

namespace App\Entity\Tracking;

enum TrackingEvent: int {
    case START = 1;
    case STOP = 2;
    case PAUSE = 3;
}
