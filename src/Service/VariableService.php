<?php


namespace App\Service;


class VariableService
{

    public const SENSOR_NAME = "nomcapteur";
    public const SENSOR_CODE = "codecapteur";
    public const ALERT_DATE = "datealerte";
    public const DATA = "data";

    public const ALERT_DICTIONARY = [
        self::SENSOR_NAME => "Nom du capteur qui a déclenché l'alerte",
        self::SENSOR_CODE => "Code du capteur qui a déclenché l'alerte",
        self::ALERT_DATE => "Date et heure du déclenchement de l'alerte",
        self::DATA => "Fonctionne seulement pour un capteur de type température. La température ayant déclenché l'alerte sera alors la donnée remontée",
    ];

    public function replaceVariables(string $message, array $values): string {
        foreach($values as $variable => $value) {
            $message = str_replace("@$variable", $value, $message);
        }

        return $message;
    }

}
