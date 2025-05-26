<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231017092551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("import")->hasColumn("type")) {
            $this->addSql("ALTER TABLE import ADD COLUMN type_id INT NOT NULL");
            $this->addSql("INSERT INTO category_type (label) VALUE (:importCategoryLabel)", [
                "importCategoryLabel" => CategoryType::IMPORT,
            ]);

            $this->addSql("
                INSERT INTO type (category_id, label)
                    VALUE (
                        (SELECT category_type.id
                         FROM category_type
                         WHERE category_type.label = :importCategory
                         LIMIT 1),
                        :importLabel
                    )", [
                "importCategory" => CategoryType::IMPORT,
                "importLabel" => Type::LABEL_UNIQUE_IMPORT,
            ]);

            $this->addSql("
                UPDATE import SET type_id = (
                    SELECT type.id
                    FROM type
                        INNER JOIN category_type ON category_type.id = type.category_id AND category_type.label = :importCategory
                    WHERE type.label = :importLabel
                    ORDER BY type.id, category_type.id DESC
                    LIMIT 1)
                WHERE 1
            ", [
                "importCategory" => CategoryType::IMPORT,
                "importLabel" => Type::LABEL_UNIQUE_IMPORT,
            ]);

            $this->addSql("
                UPDATE statut
                    INNER JOIN categorie_statut ON statut.categorie_id = categorie_statut.id
                SET statut.nom = :newStatusName, statut.code = :newStatusName
                WHERE statut.code = :oldStatusName
                  AND categorie_statut.nom = :importStatusCategory
            ", [
                "oldStatusName" => "planifié",
                "newStatusName" => Import::STATUS_UPCOMING,
                "importStatusCategory" => CategorieStatut::IMPORT,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
