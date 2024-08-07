<?php
namespace App\Entity\Fields;

use ReflectionEnum;
use ReflectionException;

enum FixedFieldEnum: string
{
    case id = "Id";
    case number = "Numéro";
    case createdAt = "Date de création";
    case expectedAt = "Date attendue";
    case createdBy = "Créé par";
    case treatedBy = "Traité par";
    case type = "Type";
    case status = "Statut";
    case dropLocation = "Emplacement de dépose";
    case pickLocation = "Emplacement de prise";
    case location = "Emplacement";
    case lineCount = "Nombre de lignes";
    case manufacturingOrderNumber = "Numéro d'OF";
    case productArticleCode = "Code produit/article";
    case quantity = "Quantité";
    case emergency = "Urgence";
    case projectNumber = "Numéro projet";
    case comment = "Commentaire";
    case attachments = 'Pièces jointes';
    case name = "Nom";
    case description = "Description";
    case maximumTrackingDelay = "Délai maximum de Tracabilité";
    case zone = "Zone";
    case allowedNatures = "Natures autorisées";
    case allowedTemperatures = "Températures autorisées";
    case allowedDeliveryTypes = "Types de livraisons autorisés";
    case allowedCollectTypes= "Types de collectes autorisés";
    case isDeliveryPoint = "Point de livraison";
    case isOngoingVisibleOnMobile = "Encours visible sur nomade";
    case signatories = "Signataire(s)";
    case email = "Email";
    case sendEmailToManagers = "Envoi d'email à chaque dépose aux responsables de l'emplacement";
    case managers = "Responsable(s)";
    case unitPrice = "Prix unitaire";
    case address = "Adresse";
    case phoneNumber = "Téléphone";
    case receiver = "Destinataire";
    case receivers = "Destinataire(s)";
    case urgent = "Urgent";
    case code = "Code";
    case possibleCustoms = "Douanes possible";
    case orderNumber = "N° commande";
    case destination = "Destination";
    case customerName = "Client";
    case customerPhone = "Téléphone client";
    case customerRecipient = "A l'attention de";
    case customerAddress = "Adresse de livraison";
    case carrier = "Transporteur";
    case businessUnit = "Business unit";
    case requester = "Demandeur";
    case carrierTrackingNumber = "N° tracking transporteur";
    case carrierTrackingNumberReserve = "Réserve sur n° tracking";
    case emails = "Email(s)";
    case object = "Objet";
    case pickingLocation = "Point de collecte";
    case quantityToPick = "Quantité à collecter";
    case operator = "Opérateur";
    case driver = "Chauffeur";

    public static function fromCase(string $case): string|null {
        try {
            return (new ReflectionEnum(self::class))
                ->getCase($case)
                ->getValue()
                ->value;
        } catch (ReflectionException) {
            return null;
        }
    }
}
