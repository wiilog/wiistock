<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200907142945 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE handling CHANGE `date` `creation_date` datetime');
        $this->addSql('ALTER TABLE handling CHANGE `date_attendue` `desired_date` datetime');
        $this->addSql('ALTER TABLE handling CHANGE `date_end` `validation_date` datetime');
        $this->addSql('ALTER TABLE handling CHANGE `libelle` `subject` text');
        $this->addSql('ALTER TABLE handling CHANGE `demandeur_id` `requester_id` integer');
        $this->addSql('ALTER TABLE handling CHANGE commentaire `comment` text');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
