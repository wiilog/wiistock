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
        $this->addSql("UPDATE action SET label = 'afficher unité logistique' WHERE label LIKE 'afficher colis'");
        $this->addSql("UPDATE action SET label = 'ajouter unité logistique' WHERE label LIKE 'ajouter colis'");
        $this->addSql("UPDATE action SET label = 'modifier unité logistique' WHERE label LIKE 'modifier colis'");
        $this->addSql("UPDATE action SET label = 'supprimer unité logistique' WHERE label LIKE 'supprimer colis'");
        $this->addSql("UPDATE action SET label = 'gérer unité logistique' WHERE label LIKE 'gérer colis'");
        $this->addSql("UPDATE action SET label = 'afficher nature d\'unité logistique' WHERE label LIKE 'afficher nature de colis'");

        $this->addSql("UPDATE sub_menu SET label = 'unités logistiques' WHERE label LIKE 'colis'");

        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'unités logistiques en encours sur les emplacements sélectionnés' WHERE hint LIKE 'Nombre de colis en encours sur les emplacements sélectionnés'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'unités logistiques à traiter en fonction des emplacements d\'origine et de destination paramétrés' WHERE hint LIKE 'Nombre de colis à traiter en fonction des emplacements d\'origine et de destination paramétrés'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'unités logistiques par natures paramétrées présentes sur la durée paramétrée sur l\'ensemble des emplacements paramétrés' WHERE hint LIKE 'Nombre de colis par natures paramétrées présents sur la durée paramétrée sur l\'ensemble des emplacements paramétrés'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'unités logistiques présentes sur les emplacements de dépose paramétrés' WHERE hint LIKE 'Nombre d\'unité logistique présents sur les emplacements de dépose paramétrés'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Les 100 unités logistiques les plus anciennes ayant dépassé le délai de présence sur leur emplacement' WHERE hint LIKE 'Les 100 colis les plus anciens ayant dépassé le délai de présence sur leur emplacement'");
        $this->addSql("UPDATE dashboard_component_type SET name = 'Unité logistique à traiter en provenance' WHERE name LIKE 'Colis à traiter en provenance'");
        $this->addSql("UPDATE dashboard_component_type SET name = 'Nombre d\'unités logistiques distribuées en dépose' WHERE name LIKE 'Nombre de colis distribués en dépose'");

        $this->addSql("UPDATE translation_source SET tooltip = 'Page Flux - Arrivages :\\nDétails arrivages - Liste des unités logistiques - Bouton\\nDétails arrivages - Liste des unités logistiques - Modale Ajouter unité logistique' WHERE tooltip LIKE 'Page Flux - Arrivages :\\nDétails arrivages - Liste des colis - Bouton\\nDétails arrivages - Liste des colis - Modale Ajouter colis '");
        $this->addSql("UPDATE translation SET translation = 'Liste des unités logistiques' WHERE translation LIKE 'Liste des colis'");
        $this->addSql("UPDATE translation SET translation = 'Vous êtes sur le point de dégrouper le groupe {1}. Les unités logistiques suivantes seront déposées sur l\'emplacement sélectionné : {2}.' WHERE translation LIKE 'Vous êtes sur le point de dégrouper le groupe {1}. Les colis suivant seront déposés sur l\'emplacement sélectionné : {2}.'");
        $this->addSql("UPDATE translation SET translation = 'You are about to ungroup the group {1}. The following logistic units will be dropped off at the selected location : {2}.' WHERE translation LIKE 'You are about to ungroup the group {1}. The following packages will be dropped off at the selected location : {2}.'");
        $this->addSql("UPDATE translation_source SET tooltip = 'Détails acheminements - Liste des unités logistiques - Nom de colonnes\\nEmails' WHERE tooltip LIKE 'Détails acheminements - Liste des colis - Nom de colonnes\\nEmails'");
        $this->addSql("UPDATE translation_source SET tooltip = 'Détails acheminements - Liste des unités logistiques - Nom de colonnes\\nPDF bon acheminement\\nPDF lettre de voiture' WHERE tooltip LIKE 'Détails acheminements - Liste des colis - Nom de colonnes\\nPDF bon acheminement\\nPDF lettre de voiture'");
        $this->addSql("UPDATE translation_source SET tooltip = 'L\'acheminement contient plus de {1} UL' WHERE tooltip LIKE 'L\'acheminement contient plus de {1} colis'");

        $this->addSql("UPDATE setting SET label = 'AUTO_PRINT_LU' WHERE label LIKE 'AUTO_PRINT_COLIS'");
        $this->addSql("UPDATE setting SET label = 'INCLURE_NOMBRE_DE_UL_SUR_ETIQUETTE' WHERE label LIKE 'INCLURE_NOMBRE_DE_COLIS_SUR_ETIQUETTE'");

        $this->addSql("UPDATE filtre_sup SET field = 'UL' WHERE label LIKE 'colis'");
    }
}
