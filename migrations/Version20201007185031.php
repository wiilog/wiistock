<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201007185031 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {

        $this->addSql('ALTER TABLE mouvement_traca RENAME TO tracking_movement');
        $this->addSql('ALTER TABLE pack ADD article_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pack ADD reference_article_id INT DEFAULT NULL');

        $this->addSql('
            UPDATE pack
            SET article_id = (SELECT tracking_movement.article_id FROM tracking_movement WHERE tracking_movement.pack_id = pack.id LIMIT 1)
        ');
        $this->addSql('
            UPDATE pack
            SET reference_article_id = (SELECT tracking_movement.reference_article_id FROM tracking_movement WHERE tracking_movement.pack_id = pack.id LIMIT 1)
        ');


    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
