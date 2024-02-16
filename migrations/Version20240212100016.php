<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240212100016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable("tracking_movement")->hasColumn("order_index")) {
            $this->addSql("ALTER TABLE tracking_movement ADD COLUMN order_index INT NOT NULL DEFAULT 0");
        }

        $this->addSql("UPDATE tracking_movement SET order_index = id WHERE order_index IS NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
