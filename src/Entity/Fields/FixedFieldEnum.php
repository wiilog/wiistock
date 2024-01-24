<?php
namespace App\Entity\Fields;

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
}
