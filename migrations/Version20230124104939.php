<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230124104939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD native_country_id INT DEFAULT NULL, ADD rfidtag VARCHAR(15) DEFAULT NULL, ADD delivery_note VARCHAR(255) DEFAULT NULL, ADD purchase_order VARCHAR(255) DEFAULT NULL, ADD manifacturing_date DATE DEFAULT NULL, ADD production_date DATE DEFAULT NULL');
        $this->addSql('CREATE TABLE storage_rule (reference_article_id INT NOT NULL, location_id INT NOT NULL, securityQuantity INT DEFAULT NULL, conditioningQuantity INT DEFAULT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
