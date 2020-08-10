<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200804101730 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {

        $this->addSql('ALTER TABLE pack ADD last_tracking_id INT');

        $this->addSql('UPDATE pack SET last_tracking_id = last_drop_id');

        $this->addSql('UPDATE pack SET last_tracking_id = (
            SELECT id
            FROM mouvement_traca
            WHERE mouvement_traca.colis = pack.code
            ORDER BY datetime DESC
            LIMIT 1
        ) WHERE last_tracking_id IS NULL');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
