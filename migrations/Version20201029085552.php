<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201029085552 extends AbstractMigration {

    private const REFERENCES = [
        "Actions" => "actions",
        "Libellé" => "label",
        "Référence" => "reference",
        "Type" => "type",
        "Statut" => "status",
        "Quantité disponible" => "availableQuantity",
        "Quantité en stock" => "stockQuantity",
        "Code barre" => "barCode",
        "Emplacement" => "location",
        "Commentaire" => "comment",
        "Commentaire d'urgence" => "emergencyComment",
        "Seuil d'alerte" => "warningThreshold",
        "Seuil de sécurité" => "securityThreshold",
        "Prix unitaire" => "unitPrice",
        "Dernier inventaire" => "lastInventory",
        "Urgence" => "emergency",
        "Synchronisation nomade" => "mobileSync",
        "Gestion de stock" => "stockManagement",
        "Gestionnaire(s)" => "managers"
    ];

    private const ARTICLES = [
        "Actions" => "actions",
        "Libellé" => "label",
        "Référence" => "reference",
        "Référence article" => "articleReference",
        "Code barre" => "barCode",
        "Type" => "type",
        "Statut" => "status",
        "Quantité" => "quantity",
        "Emplacement" => "location",
        "Prix unitaire" => "unitPrice",
        "Dernier inventaire" => "lastInventory",
        "Lot" => "batch",
        "Date d'entrée en stock" => "stockEntryDate",
        "Date de péremption" => "expiryDate",
        "Commentaire" => "comment"
    ];

    private const ARRIVALS = [
        'Date' => 'date',
        'NumeroArrivage' => 'arrivalNumber',
        'Transporteur' => 'carrier',
        'Chauffeur' => 'driver',
        'NoTracking' => 'trackingCarrierNumber',
        'NumeroCommandeList' => 'orderNumber',
        'Fournisseur' => 'provider',
        'Destinataire' => 'receiver',
        'Acheteurs' => 'buyers',
        'NbUM' => 'nbUm',
        'Statut' => 'status',
        'Utilisateur' => 'user',
        'Duty' => 'custom',
        'Frozen' => 'frozen',
        'Urgent' => 'emergency'
    ];

    private $ff;

    public function up(Schema $schema): void {
        $users = $this->connection->executeQuery("SELECT * FROM utilisateur");
        $this->initializeFreeFields();

        foreach($users as $user) {
            $referencesSearch = $this->adapt(self::REFERENCES, Utilisateur::SEARCH_DEFAULT, $user["recherche"]);
            $articlesSearch = $this->adapt(self::ARTICLES, Utilisateur::SEARCH_DEFAULT, $user["recherche_for_article"]);

            $references = $this->adapt(self::REFERENCES, Utilisateur::COL_VISIBLE_REF_DEFAULT, $user["column_visible"]);
            $articles = $this->adapt(self::ARTICLES, Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT, $user["columns_visible_for_article"]);
            $dispatch = $this->adapt(null, Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT, $user["columns_visible_for_dispatch"]);
            $trackingMovement = $this->adapt(null, Utilisateur::COL_VISIBLE_TRACKING_MOVEMENT_DEFAULT, $user["columns_visible_for_tracking_movement"]);
            $arrival = $this->adapt(self::ARRIVALS, Utilisateur::COL_VISIBLE_ARR_DEFAULT, $user["columns_visible_for_arrivage"]);
            $dispute = $this->adapt(null, Utilisateur::COL_VISIBLE_LIT_DEFAULT, $user["columns_visible_for_litige"]);

            $referencesStr = ($references && $references !== 'null') ? "'$references'" : 'NULL';
            $articlesStr = ($articles && $articles !== 'null') ? "'$articles'" : 'NULL';
            $dispatchStr = ($dispatch && $dispatch !== 'null') ? "'$dispatch'" : 'NULL';
            $trackingMovementStr = ($trackingMovement && $trackingMovement !== 'null') ? "'$trackingMovement'" : 'NULL';
            $arrivalStr = ($arrival && $arrival !== 'null') ? "'$arrival'" : 'NULL';
            $disputeStr = ($dispute && $dispute !== 'null') ? "'$dispute'" : 'NULL';

            $this->addSql("
                UPDATE utilisateur
                SET column_visible = {$referencesStr},
                    columns_visible_for_article = {$articlesStr},
                    columns_visible_for_dispatch = {$dispatchStr},
                    columns_visible_for_tracking_movement = {$trackingMovementStr},
                    columns_visible_for_arrivage = {$arrivalStr},
                    columns_visible_for_litige = {$disputeStr},
                    recherche = '$referencesSearch',
                    recherche_for_article = '$articlesSearch'
                WHERE id = {$user['id']}
            ");
        }
    }

    private function initializeFreeFields() {
        $ff = $this->connection->executeQuery("SELECT id, label FROM free_field");
        $adapted = [];

        foreach($ff as $field) {
            $adapted[$field["label"]] = 'free_field_' . $field["id"];
        }

        $this->ff = $adapted;
    }

    private function adapt(?array $template, array $default, ?string $items): ?string {
        if($items == null) {
            return json_encode($default);
        }

        $output = [];
        $items = json_decode($items);

        foreach($items as $item) {
            $item = $template[$item] ?? $this->ff[$item] ?? ($template ? null : $item);

            if($item) {
                $output[] = $item;
            }
        }

        return json_encode($output);
    }

}
