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
final class Version20200908101115 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add standard type on existing handling';
    }

    public function up(Schema $schema) : void
    {
        $handlingTypeCategory = CategoryType::DEMANDE_HANDLING;
        $typeHandlingSelect = "
            SELECT type.id AS id
            FROM type
            INNER JOIN category_type on type.category_id = category_type.id
            WHERE category_type.label = '${handlingTypeCategory}' LIMIT 1
        ";

        $this->addSql('ALTER TABLE handling ADD type_id INT DEFAULT NULL');
        $this->addSql("
            UPDATE handling
            SET handling.type_id = (${typeHandlingSelect})
        ");

        $handlingStatusCategory = CategorieStatut::HANDLING;
        $this->addSql("
            UPDATE statut
            SET statut.type_id = (${typeHandlingSelect})
            WHERE statut.categorie_id = (SELECT categorie_statut.id AS id FROM categorie_statut WHERE categorie_statut.nom = '${handlingStatusCategory}' LIMIT 1)
        ");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
