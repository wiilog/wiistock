<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200826224141 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $categoryStatusDispatch = CategorieStatut::DISPATCH;
        $typeLabelStandard = Type::LABEL_STANDARD;
        $categoryTypeAcheminement = CategoryType::DEMANDE_DISPATCH;

        $sqlCategoryTypeAcheminement = "SELECT id FROM category_type WHERE category_type.label = '${categoryTypeAcheminement}' LIMIT 1";
        $sqlTypeAcheminement = "SELECT id FROM type WHERE category_id = (${sqlCategoryTypeAcheminement}) and label = '$typeLabelStandard' LIMIT 1";

        $this->addSql('ALTER TABLE statut ADD type_id INTEGER');
        $this->addSql("
            UPDATE statut
            INNER JOIN categorie_statut ON statut.categorie_id = categorie_statut.id
            SET statut.type_id = (${sqlTypeAcheminement})
            WHERE statut.type_id IS NULL AND categorie_statut.nom = '${categoryStatusDispatch}'
        ");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
