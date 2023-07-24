<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230419083043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reference_article ADD sheet_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reference_article ADD CONSTRAINT FK_54AABCE8B1206A5 FOREIGN KEY (sheet_id) REFERENCES attachment (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54AABCE8B1206A5 ON reference_article (sheet_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reference_article DROP FOREIGN KEY FK_54AABCE8B1206A5');
        $this->addSql('DROP INDEX UNIQ_54AABCE8B1206A5 ON reference_article');
        $this->addSql('ALTER TABLE reference_article DROP sheet_id');
    }
}
