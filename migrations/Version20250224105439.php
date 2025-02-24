<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224105439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sleeping_stock_plan table';
    }

    public function up(Schema $schema): void {
        if(!$schema->hasTable("sleeping_stock_plan")) {
            // this up() migration is auto-generated, please modify it to your needs
            $this->addSql('CREATE TABLE sleeping_stock_plan (id INT AUTO_INCREMENT NOT NULL, schedule_rule_id INT DEFAULT NULL, type_id INT NOT NULL, last_run DATETIME DEFAULT NULL, max_storage_time INT NOT NULL, UNIQUE INDEX UNIQ_7E750F38F9242FE0 (schedule_rule_id), UNIQUE INDEX UNIQ_7E750F38C54C8C93 (type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE sleeping_stock_plan ADD CONSTRAINT FK_7E750F38F9242FE0 FOREIGN KEY (schedule_rule_id) REFERENCES schedule_rule (id)');
            $this->addSql('ALTER TABLE sleeping_stock_plan ADD CONSTRAINT FK_7E750F38C54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        }
    }

    public function down(Schema $schema): void {
        if($schema->hasTable("sleeping_stock_plan")) {
            // this down() migration is auto-generated, please modify it to your needs
            $this->addSql('ALTER TABLE sleeping_stock_plan DROP FOREIGN KEY FK_7E750F38F9242FE0');
            $this->addSql('ALTER TABLE sleeping_stock_plan DROP FOREIGN KEY FK_7E750F38C54C8C93');
            $this->addSql('DROP TABLE sleeping_stock_plan');
        }
    }
}
