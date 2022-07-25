<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220711134428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->hasTable("attachment_tracking_movement")) {
            $this->addSql('CREATE TABLE attachment_tracking_movement (attachment_id INT NOT NULL, tracking_movement_id INT NOT NULL, PRIMARY KEY(attachment_id, tracking_movement_id))');
            $this->addSql('INSERT INTO attachment_tracking_movement (attachment_id, tracking_movement_id)
                                SELECT id AS attachment_id , mvt_traca_id AS tracking_movement_id
                                FROM attachment
                                WHERE mvt_traca_id IS NOT NULL
            ');
        }
        //$this->addSql('ALTER TABLE attachment DROP mvt_traca_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
