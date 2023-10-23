<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Type;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231017092551 extends AbstractMigration
{

    public function __construct(Connection $connection, LoggerInterface $logger) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("import")->hasColumn("type")) {
            $this->addSql("ALTER TABLE import ADD COLUMN type_id INT NOT NULL");
            $this->addSql("INSERT INTO category_type (label) VALUE (:category_type)", [
                "category_type" => CategoryType::IMPORT,
            ]);

            $this->addSql("INSERT INTO type (category_id, label) VALUE ((SELECT LAST_INSERT_ID()), :type)", [
                "type" => Type::LABEL_UNIQUE_IMPORT,
            ]);

            $this->addSql("UPDATE import SET type_id = (SELECT LAST_INSERT_ID())");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
