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
    case allowedDeliveryTypes = "Types de commandes autorisés";
    case allowedCollectTypes= "Types de collectes autorisés";
    case isDeliveryPoint = "Point de livraison";
    case isOngoingVisibleOnMobile = "Encours visible sur nomade";
    case signatories = "Signataires";
    case email = "Email";
    case sendEmailToManagers = "Envoi d'email à chaque dépose aux responsables de l'emplacement";
    case managers = "Responsables";

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
