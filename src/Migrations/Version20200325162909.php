<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200325162909 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'remove "afficher afficher chauffeurs" action';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DELETE FROM action WHERE label = "afficher afficher chauffeurs"');
    }

    public function down(Schema $schema) : void
    {
    }
}
