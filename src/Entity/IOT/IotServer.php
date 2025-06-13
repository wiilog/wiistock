<?php
namespace App\Entity\IOT;

enum IotServer: string {
    case Orange = "ORANGE";
    case ChirpStack = "CHIRPSTACK";
    case NodeRed = "NODERED";
    case Ruptela = "RUPTELA";
}
