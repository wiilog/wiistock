<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Handling;
use DateTime;
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
        $this->addSql('ALTER TABLE handling CHANGE `demandeur_id` `requester_id` INTEGER');
        $this->addSql('ALTER TABLE handling CHANGE `commentaire` `comment` TEXT');

        $this->addSql('ALTER TABLE handling ADD number VARCHAR(64) DEFAULT NULL');

        $handlings = $this->connection
            ->executeQuery('SELECT id, creation_date FROM handling')
            ->fetchAll();

        $daysCounter = [];

        foreach ($handlings as $handling) {
            $creationDate = DateTime::createFromFormat('Y-m-d H:i:s', $handling['date']);
            $dateStr = $creationDate->format('Ymd');

            $dayCounterKey = Handling::PREFIX_NUMBER . $dateStr;

            if (!isset($daysCounter[$dayCounterKey])) {
                $daysCounter[$dayCounterKey] = 0;
            }

            $daysCounter[$dayCounterKey]++;
            $suffix = '';
            if ($daysCounter[$dayCounterKey] < 10) {
                $suffix = '000';
            } else if ($daysCounter[$dayCounterKey] < 100) {
                $suffix = '00';
            } else if ($daysCounter[$dayCounterKey] < 1000) {
                $suffix = '0';
            }

            $id = $handling['id'];
            $dispatchNumber = Handling::PREFIX_NUMBER . $dateStr . $suffix . $daysCounter[$dayCounterKey];
            $sqlDispatchNumber = ("UPDATE handling SET number = '$dispatchNumber' WHERE handling.id = '$id'");
            $this->addSql($sqlDispatchNumber);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
