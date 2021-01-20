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
final class Version20210120095108 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::COLLECT_ORDER . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::ORDRE_COLLECTE . "'
        ");

        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::DELIVERY_ORDER . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::ORDRE_LIVRAISON . "'
        ");

        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::PREPARATION_ORDER . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::PREPARATION . "'
        ");

        $typeId = $this->connection->executeQuery("
            SELECT type.id AS typeId
            FROM type
            LEFT JOIN category_type ct on type.category_id = ct.id
            WHERE ct.label = '" . CategoryType::TRANSFER_ORDER . "'
        ")->fetchColumn();
        $typeId = intval($typeId);

        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut cs on statut.categorie_id = cs.id
            SET type_id = ${typeId}
            WHERE cs.nom = '" . CategorieStatut::TRANSFER_ORDER . "'
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
