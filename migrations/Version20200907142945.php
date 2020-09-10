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
        $this->addSql('ALTER TABLE handling CHANGE `date` `creation_date` DATETIME');
        $this->addSql('ALTER TABLE handling CHANGE `date_attendue` `desired_date` DATETIME');
        $this->addSql('ALTER TABLE handling CHANGE `date_end` `validation_date` DATETIME');
        $this->addSql('ALTER TABLE handling CHANGE `libelle` `subject` TEXT');
        $this->addSql('ALTER TABLE handling CHANGE `demandeur_id` `requester_id` INT');
        $this->addSql('ALTER TABLE handling CHANGE commentaire `comment` TEXT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
