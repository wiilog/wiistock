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
final class Version20210120093019 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO category_type (label) VALUES ('" . CategoryType::COLLECT_ORDER . "')");
        $this->addSql("INSERT INTO type (category_id, label) VALUES ((SELECT LAST_INSERT_ID()), '" . Type::LABEL_STANDARD . "')");
        $this->addSql("INSERT INTO category_type (label) VALUES ('" . CategoryType::DELIVERY_ORDER . "')");
        $this->addSql("INSERT INTO type (category_id, label) VALUES ((SELECT LAST_INSERT_ID()), '" . Type::LABEL_STANDARD . "')");
        $this->addSql("INSERT INTO category_type (label) VALUES ('" . CategoryType::PREPARATION_ORDER . "')");
        $this->addSql("INSERT INTO type (category_id, label) VALUES ((SELECT LAST_INSERT_ID()), '" . Type::LABEL_STANDARD . "')");
        $this->addSql("INSERT INTO category_type (label) VALUES ('" . CategoryType::TRANSFER_ORDER . "')");
        $this->addSql("INSERT INTO type (category_id, label) VALUES ((SELECT LAST_INSERT_ID()), '" . Type::LABEL_STANDARD . "')");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
