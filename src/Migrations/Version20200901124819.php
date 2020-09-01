<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200901124819 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Initialize quantity pack & tracking movement to 1';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pack ADD quantity INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_traca ADD quantity INTEGER DEFAULT NULL');
        $this->addSql('UPDATE pack SET quantity = 1');
        $this->addSql('UPDATE mouvement_traca SET quantity = 1');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
