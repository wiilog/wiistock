<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200524150724 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE non_worked_days (id INT AUTO_INCREMENT NOT NULL, day varchar(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
//        $this->addSql('DROP TABLE column_hidden');
    }
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

//        $this->addSql('CREATE TABLE column_hidden (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, page VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, value JSON NOT NULL, UNIQUE INDEX UNIQ_286A4C72A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
//        $this->addSql('ALTER TABLE column_hidden ADD CONSTRAINT FK_286A4C72A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('DROP TABLE non_worked_days');
    }
}
