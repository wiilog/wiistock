<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230525153340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if ($schema->hasTable("shipping_request_expected_line")
            && $schema->getTable("shipping_request_expected_line")->hasColumn("price")) {
            $this->addSql('ALTER TABLE shipping_request_expected_line RENAME COLUMN price TO unit_price');
        }

        if ($schema->hasTable("shipping_request_expected_line")
            && $schema->getTable("shipping_request_expected_line")->hasColumn("weight")) {
            $this->addSql('ALTER TABLE shipping_request_expected_line RENAME COLUMN weight TO unit_weight');
        }

        if ($schema->hasTable("shipping_request_expected_line")
            && !$schema->getTable("shipping_request_expected_line")->hasColumn("total_price")) {
            $this->addSql('ALTER TABLE shipping_request_expected_line ADD total_price FLOAT NULL');
            $this->addSql('UPDATE shipping_request_expected_line SET total_price = quantity * unit_price');
        }
    }

    public function down(Schema $schema): void
    {

    }
}
