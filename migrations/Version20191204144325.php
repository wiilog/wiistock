<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191204144325 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'fill validationDate field';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE collecte ADD COLUMN validation_date DATETIME');
        $this->addSql('UPDATE collecte c SET c.validation_date = (SELECT oc.date FROM ordre_collecte oc WHERE oc.demande_collecte_id = c.id LIMIT 1)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE collecte DROP COLUMN validation_date');
        $this->addSql('UPDATE collecte c SET c.validation_date = null');
    }
}
