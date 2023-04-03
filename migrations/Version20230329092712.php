<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230329092712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // table translation
        $this->addSql("UPDATE translation_category SET label = 'Arrivages UL' WHERE label = 'Flux - Arrivages'");
        $this->addSql("UPDATE translation_category SET label = 'Détails arrivage UL - Entête' WHERE label = 'Détails arrivage - Entête'");
        $this->addSql("UPDATE translation_category SET label = 'Modale création nouvel arrivage UL' WHERE label = 'Modale création nouvel arrivage'");
        $this->addSql("UPDATE translation_category SET label = 'Détails arrivage UL - Liste des litiges' WHERE label = 'Détails arrivage - Liste des litiges'");
        $this->addSql("UPDATE translation_category SET label = 'Email arrivage UL' WHERE label = 'Email arrivage'");

        $this->addSql("UPDATE translation SET translation = 'Arrivages unités logistiques' WHERE translation = 'Flux - arrivages'");
        $this->addSql("UPDATE translation SET translation = 'Logistics units arrivals' WHERE translation = 'Arrivals'");

        $this->addSql("UPDATE translation SET translation = 'N° d\'arrivage UL' WHERE translation = 'N° d\'arrivage'");
        $this->addSql("UPDATE translation SET translation = 'LU Arrival number' WHERE translation = 'Arrival number'");

        $this->addSql("UPDATE translation SET translation = 'FOLLOW GT // Arrivage UL' WHERE translation = 'FOLLOW GT // Arrivage'");
        $this->addSql("UPDATE translation SET translation = 'FOLLOW GT // LU Arrivals' WHERE translation = 'FOLLOW GT // Arrivals'");

        $this->addSql("UPDATE translation SET translation = 'FOLLOW GT // Arrivage UL urgent' WHERE translation = 'FOLLOW GT // Arrivage urgent'");
        $this->addSql("UPDATE translation SET translation = 'FOLLOW GT // Urgent LU arrival' WHERE translation = 'FOLLOW GT // Urgent arrival'");

        $this->addSql("UPDATE translation SET translation = 'Arrivage UL reçu : le {1} à {2}' WHERE translation = 'Arrivage reçu : le {1} à {2}'");
        $this->addSql("UPDATE translation SET translation = 'LU Arrival received : on {1} at {2}' WHERE translation = 'Arrival received : on {1} at {2}'");

        $this->addSql("UPDATE translation SET translation = 'Nouvel arrivage UL' WHERE translation = 'Nouvel arrivage'");
        $this->addSql("UPDATE translation SET translation = 'New LU arrival' WHERE translation = 'New arrival'");

        $this->addSql("UPDATE translation SET translation = 'Arrivages UL' WHERE translation = 'Arrivages'");
        $this->addSql("UPDATE translation SET translation = 'LU Arrivals' WHERE translation = 'Arrivals'");

        $this->addSql("UPDATE translation SET translation = 'Arrivage UL' WHERE translation = 'Arrivage'");
        $this->addSql("UPDATE translation SET translation = 'LU Arrival' WHERE translation = 'Arrival'");

        $this->addSql("UPDATE translation SET translation = 'arrivage UL' WHERE translation = 'arrivage'");
        $this->addSql("UPDATE translation SET translation = 'LU arrival' WHERE translation = 'arrival'");

        $this->addSql("UPDATE translation SET translation = 'Modifier arrivage UL' WHERE translation = 'Modifier arrivage'");
        $this->addSql("UPDATE translation SET translation = 'Edit LU arrival' WHERE translation = 'Edit arrival'");

        $this->addSql("UPDATE translation SET translation = 'Supprimer l\'arrivage UL' WHERE translation = 'Supprimer l\'arrivage'");
        $this->addSql("UPDATE translation SET translation = 'Delete the LU arrival' WHERE translation = 'Delete the arrival'");

        $this->addSql("UPDATE translation SET translation = 'Voulez-vous réellement supprimer cet arrivage UL ?' WHERE translation = 'Voulez-vous réellement supprimer cet arrivage ?'");
        $this->addSql("UPDATE translation SET translation = 'Do you really want to delete this LU arrival ?' WHERE translation = 'Do you really want to delete this arrival ?'");

        $this->addSql("UPDATE translation SET translation = '(attention, un litige a été créé sur cet arrivage UL : il sera également supprimé)' WHERE translation = '(attention, un litige a été créé sur cet arrivage : il sera également supprimé)'");
        $this->addSql("UPDATE translation SET translation = '(attention, a dispute has been created on this LU arrival: it will also be deleted)' WHERE translation = '(attention, a dispute has been created on this arrival: it will also be deleted)'");

        $this->addSql("UPDATE translation SET translation = 'Un autre arrivage UL est en cours de création, veuillez réessayer' WHERE translation = 'Un autre arrivage est en cours de création, veuillez réessayer'");
        $this->addSql("UPDATE translation SET translation = 'Another LU arrival is being created, please try again' WHERE translation = 'Another arrival is being created, please try again'");

        $this->addSql("UPDATE translation SET translation = 'Un autre litige d\'arrivage UL est en cours de création, veuillez réessayer' WHERE translation = 'Un autre litige d\'arrivage est en cours de création, veuillez réessayer'");
        $this->addSql("UPDATE translation SET translation = 'Another LU arrival dispute is being created, please try again' WHERE translation = 'Another arrival dispute is being created, please try again'");

        $this->addSql("UPDATE translation SET translation = 'Imprimer arrivage UL' WHERE translation = 'Imprimer arrivage'");
        $this->addSql("UPDATE translation SET translation = 'Print LU arrival' WHERE translation = 'Print arrival'");

        $this->addSql("UPDATE translation SET translation = 'Cet arrivage UL est à traiter en URGENCE' WHERE translation = 'Cet arrivage est à traiter en URGENCE'");
        $this->addSql("UPDATE translation SET translation = 'This LU arrival needs to be dealt with urgently' WHERE translation = 'This arrival needs to be dealt with urgently'");

        $this->addSql("UPDATE translation SET translation = 'un arrivage UL' WHERE translation = 'un arrivage'");
        $this->addSql("UPDATE translation SET translation = 'a LU arrival' WHERE translation = 'a delivery'");

        $this->addSql("UPDATE translation SET translation = 'Valider arrivages UL à acheminer' WHERE translation = 'Valider arrivages à acheminer'");
        $this->addSql("UPDATE translation SET translation = 'Validate LU arrivals to transfer' WHERE translation = 'Validate arrivals to transfer'");

        $this->addSql("UPDATE translation SET translation = 'Date arrivage UL' WHERE translation = 'Date arrivage'");
        $this->addSql("UPDATE translation SET translation = 'LU arrival date' WHERE translation = 'Arrival date'");

        $this->addSql("UPDATE translation SET translation = 'Cette urgence est liée à un arrivage UL.\nVous ne pouvez pas la supprimer' WHERE translation = 'Cette urgence est liée à un arrivage.\nVous ne pouvez pas la supprimer'");
        $this->addSql("UPDATE translation SET translation = 'This emergency is linked to an LU arrival. You cannot delete it' WHERE translation = 'This emergency is linked to an arrival. You cannot delete it'");

        $this->addSql("UPDATE translation SET translation = 'Numéro d\'arrivage UL' WHERE translation = 'Numéro d\'arrivage'");
        $this->addSql("UPDATE translation SET translation = 'LU arrival number' WHERE translation = 'arrival number'");

        $this->addSql("UPDATE translation SET translation = 'Cette unité logistique est utilisé dans l\'arrivage UL {1}' WHERE translation = 'Cette unité logistique est utilisé dans l\'arrivage {1}'");
        $this->addSql("UPDATE translation SET translation = 'This logistic unit is in use in the LU arrival {1}' WHERE translation = 'This logistic unit is in use in the arrival {1}'");

        //table dashboard_component_type
        $this->addSql("UPDATE dashboard_component_type SET name = 'Nombre d\'arrivages UL quotidiens' WHERE name = 'Nombre d\'arrivages quotidiens'");
        $this->addSql("UPDATE dashboard_component_type SET name = 'Nombre d\'associations Arrivages UL - Réceptions' WHERE name = 'Nombre d\'associations Arrivages - Réceptions'");
        $this->addSql("UPDATE dashboard_component_type SET name = 'Nombre d\'arrivages UL et d\'UL quotidiens' WHERE name = 'Nombre d\'arrivages et d\'UL quotidiens'");
        $this->addSql("UPDATE dashboard_component_type SET name = 'Nombre d\'arrivages UL et d\'UL hebdomadaires' WHERE name = 'Nombre d\'arrivages et d\'UL hebdomadaires'");

        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'arrivages UL créés par jour' WHERE hint = 'Nombre d\'arrivages créés par jour'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Transporteurs ayant effectué un arrivage UL dans la journée' WHERE hint = 'Transporteurs ayant effectué un arrivage dans la journée'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'urgences sur arrivage UL encore non réceptionnées' WHERE hint = 'Nombre d\'urgences sur arrivage encore non réceptionnées'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'urgences sur arrivage UL devant être réceptionnées dans la journée' WHERE hint = 'Nombre d\'urgences sur arrivage devant être réceptionnées dans la journée'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'arrivages UL et d\'UL créés par jour' WHERE hint = 'Nombre d\'arrivages et d\'UL créés par jour'");
        $this->addSql("UPDATE dashboard_component_type SET hint = 'Nombre d\'arrivage UL et d\'UL créés par semaine' WHERE hint = 'Nombre d\'arrivage et d\'UL créés par semaine'");
    }
}
