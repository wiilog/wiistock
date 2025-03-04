<?php

namespace App\Service\Tracking;

enum TrackingTimerEvent {
    case MOVEMENT;
    case TRUCK_ARRIVAL;
    case ARRIVAL;
}
