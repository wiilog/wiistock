<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200826093645 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $typeLabelStandard = Type::LABEL_STANDARD;
        $categoryTypeAcheminement = CategoryType::DEMANDE_ACHEMINEMENT;

        $sqlCategoryTypeAcheminement = "SELECT id FROM category_type WHERE category_type.label = '$categoryTypeAcheminement' LIMIT 1";
        $sqlTypeAcheminement = "SELECT id FROM type WHERE category_id = ($sqlCategoryTypeAcheminement) and label = '$typeLabelStandard' LIMIT 1";

        $isAlreadyDefined = $this->connection
            ->executeQuery($sqlTypeAcheminement)
            ->fetchAll();

        if(empty($isAlreadyDefined)) {
            $this->addSql("INSERT INTO `type` (category_id, label) VALUES (($sqlCategoryTypeAcheminement), '$typeLabelStandard')");
        }

        $sqlTypeAcheminement = ("UPDATE acheminements SET type_id = ($sqlTypeAcheminement) WHERE type_id IS NULL");
        $this->addSql($sqlTypeAcheminement);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
