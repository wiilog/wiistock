<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224113044 extends AbstractMigration
{
    public function getDescription(): string {
        return 'create sleeping_stock_request_information table';
    }

    public function up(Schema $schema): void {
        if (!$schema->hasTable("sleeping_stock_request_information")){
            $this->addSql('CREATE TABLE sleeping_stock_request_information (id INT AUTO_INCREMENT NOT NULL, delivery_request_template_id INT NOT NULL, button_action_label VARCHAR(255) NOT NULL, INDEX IDX_5E38C28EFB91FB9F (delivery_request_template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE sleeping_stock_request_information ADD CONSTRAINT FK_5E38C28EFB91FB9F FOREIGN KEY (delivery_request_template_id) REFERENCES delivery_request_template (id)');
        }
    }

    public function down(Schema $schema): void {
        if ($schema->hasTable("sleeping_stock_request_information")){
            $this->addSql('ALTER TABLE sleeping_stock_request_information DROP FOREIGN KEY FK_5E38C28EFB91FB9F');
            $this->addSql('DROP TABLE sleeping_stock_request_information');
        }
    }
}
