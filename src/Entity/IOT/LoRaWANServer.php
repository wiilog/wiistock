<?php
namespace App\Entity\IOT;

enum LoRaWANServer: string
{
    case Orange = "ORANGE";
    case ChirpStack = "CHIRPSTACK";
    case NodeRed = "NODERED";
}
