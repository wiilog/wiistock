<?php


namespace App\Service;


class VariableService
{

    public const SENSOR_NAME = "nomcapteur";
    public const SENSOR_CODE = "codecapteur";
    public const ALERT_DATE = "datealerte";
    public const DATA = "data";

    public const PREPARATION_ORDER_NUMBER = "numordrepreparation";
    public const COLLECT_ORDER_NUMBER = "numordrecollecte";
    public const TRANSFER_ORDER_NUMBER = "numordretransfert";
    public const DISPATCH_NUMBER = "numacheminement";
    public const HANDLING_NUMBER = "numservice";

    public const DELIVERY_TYPE = "typelivraison";
    public const DISPATCH_TYPE = "typeacheminement";
    public const HANDLING_TYPE = "numservice";

    public const STATUS = "statut";
    public const SUBJECT = "objet";
    public const COLLECT_TYPE = "typecollecte";
    public const COLLECT_POINT = "pointdecollecte";
    public const ORIGIN = "origine";
    public const DESTINATION = "destination";
    public const REQUESTER = "demandeur";
    public const VALIDATION_DATE = "datevalidation";
    public const TAKE_LOCATION = "empprise";
    public const DEPOSIT_LOCATION = "empdepose";
    public const DUE_DATE = "dateecheance";
    public const ORDER_NUMBER = "numcommande";
    public const PACK_COUNT = "nbcolis";
    public const LOADING = "chargement";
    public const UNLOADING = "dechargement";
    public const EXPECTED_DATE = "dateattendue";
    public const OPERATIONS_COUNT = "nboperations";

    public const ALERT_DICTIONARY = [
        self::SENSOR_NAME => "Nom du capteur qui a déclenché l'alerte",
        self::SENSOR_CODE => "Code du capteur qui a déclenché l'alerte",
        self::ALERT_DATE => "Date et heure du déclenchement de l'alerte",
        self::DATA => "Fonctionne seulement pour un capteur de type température. La température ayant déclenché l'alerte sera alors la donnée remontée",
    ];

    public const DELIVERY_DICTIONARY = [
        self::PREPARATION_ORDER_NUMBER => "Numéro de l'ordre",
        self::DELIVERY_TYPE => "Type de la livraison",
        self::DESTINATION => "Destination de la livraison",
        self::REQUESTER => "Utilisateur ayant créé la livraison",
        self::VALIDATION_DATE => "Date de validation de la demande de livraison",
    ];

    public const COLLECT_DICTIONARY = [
        self::COLLECT_ORDER_NUMBER => "Numéro de l'ordre de collecte",
        self::COLLECT_TYPE => "Type de la collecte",
        self::DESTINATION => "Mise en stock ou destruction",
        self::REQUESTER => "Utilisateur ayant créé la collecte",
        self::COLLECT_POINT => "Emplacement où collecter les références demandées",
        self::SUBJECT => "Objet de la collecte",
        self::VALIDATION_DATE => "Date de validation de la demande de collecte",
    ];

    public const TRANSFER_DICTIONARY = [
        self::TRANSFER_ORDER_NUMBER => "Numéro de l'ordre de transfert",
        self::ORIGIN => "Origine du transfert",
        self::DESTINATION => "Destination du transfert",
        self::REQUESTER => "Utilisateur ayant créé la demande",
        self::VALIDATION_DATE => "Date de validation de la demande de transfert",
    ];

    public const DISPATCH_DICTIONARY = [
        self::DISPATCH_NUMBER => "Numéro de l'acheminement",
        self::DISPATCH_TYPE => "Type de l'acheminement",
        self::STATUS => "Statut de la demande d'acheminement",
        self::TAKE_LOCATION => "Emplacement prise",
        self::DEPOSIT_LOCATION => "Emplacement dépose",
        self::REQUESTER => "Utilisateur ayant créé la demande d'acheminement",
        self::VALIDATION_DATE => "Date de validation de la demande d'acheminement",
        self::DUE_DATE => "Date d'échéance de la demande d'acheminement",
        self::ORDER_NUMBER => "Numéro de commande de la demande d'acheminement",
        self::PACK_COUNT => "Nombre de colis (lignes) dans la demande d'acheminement",
    ];

    public const HANDLING_DICTIONARY = [
        self::HANDLING_NUMBER => "Numéro de la demande de service",
        self::HANDLING_TYPE => "Type de service",
        self::STATUS => "Statut de la demande de service",
        self::LOADING => "Endroit de chargement",
        self::UNLOADING => "Endroit de déchargement",
        self::REQUESTER => "Utilisateur ayant créé la demande de service",
        self::VALIDATION_DATE => "Date de validation de la demande de service",
        self::EXPECTED_DATE => "Date attendue de la demande de service",
        self::SUBJECT => "Objet de la demande de service",
        self::OPERATIONS_COUNT => "Nombre d'opérations à réaliser",
    ];

    public function replaceVariables(string $message, array $values): string {
        foreach($values as $variable => $value) {
            $message = str_replace("@$variable", $value, $message);
        }
        return $message;
    }

}
