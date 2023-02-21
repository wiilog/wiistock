<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221213103323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $actions = [
            "afficher unité logistique" => "afficher colis",
            "ajouter unité logistique" => "ajouter colis",
            "modifier unité logistique" => "modifier colis",
            "supprimer unité logistique" => "supprimer colis",
            "gérer unité logistique" => "gérer colis",
            "afficher nature d'unité logistique" => "afficher nature de colis",
        ];

        foreach ($actions as $new => $old) {
            $this->addSql("UPDATE action SET label = :new WHERE label LIKE :old", [
                'new' => $new,
                'old' => $old,
            ]);
        }

        $this->addSql("UPDATE sub_menu SET label = 'unités logistiques' WHERE label LIKE 'colis'");

        $dashboardComponentTypes = [
            "Nombre d'unités logistiques en encours sur les emplacements sélectionnés" => "Nombre de colis en encours sur les emplacements sélectionnés",
            "Nombre d'unités logistiques à traiter en fonction des emplacements d'origine et de destination paramétrés" => "Nombre de colis à traiter en fonction des emplacements d'origine et de destination paramétrés",
            "Nombre d'unités logistiques par natures paramétrées présentes sur la durée paramétrée sur l'ensemble des emplacements paramétrés" => "Nombre de colis par natures paramétrées présents sur la durée paramétrée sur l'ensemble des emplacements paramétrés",
            "Nombre d'unités logistiques présentes sur les emplacements de dépose paramétrés" => "Nombre d'unité logistique présents sur les emplacements de dépose paramétrés",
            "Les 100 unités logistiques les plus anciennes ayant dépassé le délai de présence sur leur emplacement" => "Les 100 colis les plus anciens ayant dépassé le délai de présence sur leur emplacement",
            "Unité logistique à traiter en provenance" => "Colis à traiter en provenance",
            "Nombre d'unités logistiques distribuées en dépose" => "Nombre de colis distribués en dépose"
        ];

        foreach ($dashboardComponentTypes as $new => $old) {
            $this->addSql("UPDATE dashboard_component_type SET hint = :new WHERE hint LIKE :old", [
                'new' => $new,
                'old' => $old,
            ]);
        }

        $dashboardComponentTypes = [
            "Unité logistique à traiter en provenance" => "Colis à traiter en provenance",
            "Nombre d'unités logistiques distribuées en dépose" => "Nombre de colis distribués en dépose"
        ];

        foreach ($dashboardComponentTypes as $new => $old) {
            $this->addSql("UPDATE dashboard_component_type SET name = :new WHERE name LIKE :old", [
                'new' => $new,
                'old' => $old,
            ]);
        }

        $translations = [
            "Liste des unités logistiques" => "Liste des colis",
            "Vous êtes sur le point de dégrouper le groupe {1}. Les unités logistiques suivantes seront déposées sur l'emplacement sélectionné : {2}." => "Vous êtes sur le point de dégrouper le groupe {1}. Les colis suivant seront déposés sur l'emplacement sélectionné : {2}.",
            "You are about to ungroup the group {1}. The following logistic units will be dropped off at the selected location : {2}." => "You are about to ungroup the group {1}. The following packages will be dropped off at the selected location : {2}."
        ];

        foreach ($translations as $new => $old) {
            $this->addSql("UPDATE translation SET translation = :new WHERE translation LIKE :old", [
                'new' => $new,
                'old' => $old,
            ]);
        }

        $translationSources = [
            "Page Flux - Arrivages :\\nDétails arrivages - Liste des unités logistiques - Bouton\\nDétails arrivages - Liste des unités logistiques - Modale Ajouter unité logistique" => "Page Flux - Arrivages :\\nDétails arrivages - Liste des colis - Bouton\\nDétails arrivages - Liste des colis - Modale Ajouter colis ",
            "Détails acheminements - Liste des unités logistiques - Nom de colonnes\\nEmails" => "Détails acheminements - Liste des colis - Nom de colonnes\\nEmails",
            "Détails acheminements - Liste des unités logistiques - Nom de colonnes\\nPDF bon acheminement\\nPDF lettre de voiture" => "Détails acheminements - Liste des colis - Nom de colonnes\\nPDF bon acheminement\\nPDF lettre de voiture",
            "L'acheminement contient plus de {1} UL" => "L'acheminement contient plus de {1} colis"
        ];

        foreach ($translationSources as $new => $old) {
            $this->addSql("UPDATE translation_source SET tooltip = :new WHERE tooltip LIKE :old", [
                'new' => $new,
                'old' => $old,
            ]);
        }

        $this->addSql("UPDATE setting SET label = 'AUTO_PRINT_LU' WHERE label LIKE 'AUTO_PRINT_COLIS'");
        $this->addSql("UPDATE setting SET label = 'INCLURE_NOMBRE_DE_UL_SUR_ETIQUETTE' WHERE label LIKE 'INCLURE_NOMBRE_DE_COLIS_SUR_ETIQUETTE'");

        $this->addSql("UPDATE filtre_sup SET field = 'UL' WHERE field LIKE 'colis'");
    }
}
