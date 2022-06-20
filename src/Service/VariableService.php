<?php


namespace App\Service;


use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\Livraison;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\TransferOrder;
use App\Helper\FormatHelper;
use RuntimeException;

class VariableService
{

    public const DICTIONNARIES = [
        Livraison::class => self::DELIVERY_DICTIONARY,
        Preparation::class => self::PREPARATION_DICTIONARY,
        OrdreCollecte::class => self::COLLECT_DICTIONARY,
        TransferOrder::class => self::TRANSFER_DICTIONARY,
        Dispatch::class => self::DISPATCH_DICTIONARY,
        Handling::class => self::HANDLING_DICTIONARY,
    ];

    public const SENSOR_NAME = "nomcapteur";
    public const SENSOR_CODE = "codecapteur";
    public const ALERT_DATE = "datealerte";
    public const DATA = "data";

    public const DELIVERY_ORDER_NUMBER = "numordrelivraison";
    public const PREPARATION_ORDER_NUMBER = "numordrepreparation";
    public const COLLECT_ORDER_NUMBER = "numordrecollecte";
    public const TRANSFER_ORDER_NUMBER = "numordretransfert";
    public const DISPATCH_NUMBER = "numacheminement";
    public const HANDLING_NUMBER = "numservice";

    public const DELIVERY_TYPE = "typelivraison";
    public const DISPATCH_TYPE = "typeacheminement";
    public const HANDLING_TYPE = "typeservice";

    public const STATUS = "statut";
    public const SUBJECT = "objet";
    public const COLLECT_TYPE = "typecollecte";
    public const COLLECT_POINT = "pointdecollecte";
    public const ORIGIN = "origine";
    public const DESTINATION = "destination";
    public const REQUESTER = "demandeur";
    public const VALIDATION_DATE = "datevalidation";
    public const CREATION_DATE = "datecreation";
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
        self::DATA => "Fonctionne seulement pour un capteur de type température. La température ayant déclenchée l'alerte sera alors la donnée remontée",
    ];

    public const DELIVERY_DICTIONARY = [
        self::DELIVERY_ORDER_NUMBER => "Numéro de l'ordre",
        self::DELIVERY_TYPE => "Type de la livraison",
        self::DESTINATION => "Destination de la livraison",
        self::REQUESTER => "Utilisateur ayant créé la livraison",
        self::VALIDATION_DATE => "Date de validation de la demande de livraison",
    ];

    public const PREPARATION_DICTIONARY = [
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
        self::CREATION_DATE => "Date de création de la demande de service",
        self::EXPECTED_DATE => "Date attendue de la demande de service",
        self::SUBJECT => "Objet de la demande de service",
        self::OPERATIONS_COUNT => "Nombre d'opérations à réaliser",
    ];

    public function replaceVariables(string $message, $valuesOrEntity): string {
        if(!is_array($valuesOrEntity)) {
            $values = $this->asValues($valuesOrEntity);
        } else {
            $values = $valuesOrEntity;
        }

        foreach($values as $variable => $value) {
            $message = str_replace("@$variable", $value, $message);
        }

        return $message;
    }

    public function asValues($entity): array {
        if ($entity instanceof Livraison) {
            return [
                self::DELIVERY_ORDER_NUMBER => $entity->getNumero(),
                self::DELIVERY_TYPE => FormatHelper::type($entity->getDemande()->getType()),
                self::DESTINATION => FormatHelper::location($entity->getPreparation()->getDemande()->getDestination()),
                self::REQUESTER => FormatHelper::user($entity->getDemande()->getUtilisateur()),
                self::VALIDATION_DATE => FormatHelper::datetime($entity->getDate()),
            ];
        } else if ($entity instanceof OrdreCollecte) {
            return [
                self::COLLECT_ORDER_NUMBER => $entity->getNumero(),
                self::COLLECT_TYPE => FormatHelper::type($entity->getDemandeCollecte()->getType()),
                self::DESTINATION => $entity->getDemandeCollecte()->isStock() ? "Mise en stock" : "Destruction",
                self::REQUESTER => FormatHelper::user($entity->getDemandeCollecte()->getDemandeur()),
                self::COLLECT_POINT => FormatHelper::location($entity->getDemandeCollecte()->getPointCollecte()),
                self::SUBJECT => $entity->getDemandeCollecte()->getObjet(),
                self::VALIDATION_DATE => FormatHelper::datetime($entity->getDate()),
            ];
        } else if ($entity instanceof Dispatch) {
            return [
                self::DISPATCH_NUMBER => $entity->getNumber(),
                self::DISPATCH_TYPE => FormatHelper::type($entity->getType()),
                self::STATUS => FormatHelper::status($entity->getStatut()),
                self::TAKE_LOCATION => FormatHelper::location($entity->getLocationFrom()),
                self::DEPOSIT_LOCATION => FormatHelper::location($entity->getLocationTo()),
                self::REQUESTER => FormatHelper::user($entity->getRequester()),
                self::VALIDATION_DATE => FormatHelper::datetime($entity->getValidationDate()),
                self::DUE_DATE => FormatHelper::date($entity->getStartDate()) . ' au ' . FormatHelper::date($entity->getEndDate()),
                self::ORDER_NUMBER => $entity->getCommandNumber(),
                self::PACK_COUNT => $entity->getDispatchPacks()->count(),
            ];
        } else if ($entity instanceof Preparation) {
            return [
                self::PREPARATION_ORDER_NUMBER => $entity->getNumero(),
                self::DELIVERY_TYPE => FormatHelper::type($entity->getDemande()->getType()),
                self::DESTINATION => FormatHelper::location($entity->getDemande()->getDestination()),
                self::REQUESTER => FormatHelper::user($entity->getDemande()->getUtilisateur()),
                self::VALIDATION_DATE => FormatHelper::datetime($entity->getDate()),
            ];
        } else if ($entity instanceof Handling) {
            return [
                self::HANDLING_NUMBER => $entity->getNumber(),
                self::HANDLING_TYPE => FormatHelper::type($entity->getType()),
                self::STATUS => FormatHelper::status($entity->getStatus()),
                self::LOADING => $entity->getSource(),
                self::UNLOADING => $entity->getDestination(),
                self::REQUESTER => FormatHelper::user($entity->getRequester()),
                self::CREATION_DATE => FormatHelper::datetime($entity->getCreationDate()),
                self::EXPECTED_DATE => FormatHelper::datetime($entity->getDesiredDate()),
                self::SUBJECT => $entity->getSubject(),
                self::OPERATIONS_COUNT => $entity->getCarriedOutOperationCount(),
            ];
        } else if ($entity instanceof TransferOrder) {
            return [
                self::TRANSFER_ORDER_NUMBER => $entity->getNumber(),
                self::ORIGIN => FormatHelper::location($entity->getRequest()->getOrigin()),
                self::DESTINATION => FormatHelper::location($entity->getRequest()->getDestination()),
                self::REQUESTER => FormatHelper::user($entity->getRequest()->getRequester()),
                self::VALIDATION_DATE => FormatHelper::datetime($entity->getRequest()->getValidationDate()),
            ];
        } else {
            throw new RuntimeException("Unsupported entity " . get_class($entity));
        }
    }

}
