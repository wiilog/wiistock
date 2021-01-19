<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210119132607 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::DEMANDE_COLLECTE . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::DEM_COLLECTE . "'
        ");

        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::DEMANDE_LIVRAISON . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::DEM_LIVRAISON . "'
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
