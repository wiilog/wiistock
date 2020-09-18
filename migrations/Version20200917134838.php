<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200917134838 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Set standard type for old arrival';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE arrivage ADD type_id INTEGER");

        $handlingTypeCategory = CategoryType::ARRIVAGE;
        $typeArrivalSelect = "
            SELECT type.id AS id
            FROM type
            INNER JOIN category_type on type.category_id = category_type.id
            WHERE category_type.label = '${handlingTypeCategory}' LIMIT 1
        ";
        $this->addSql("UPDATE arrivage SET type_id = (${typeArrivalSelect}) WHERE type_id IS NULL");

    }

    public function down(Schema $schema) : void
    {

    }
}
