<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200917101301 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migration for treated column to new statut column "state"';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE `dispatch` CHANGE `validation_date` `treated_date` DATETIME');
        $this->addSql('ALTER TABLE statut ADD state INT DEFAULT NULL');

        $statusesIdToTreated = $this->connection->executeQuery('SELECT id, treated FROM statut');

        foreach ($statusesIdToTreated as $status) {
            $id = $status['id'];
            $treated = (int) $status['treated'];
            $state = ($treated === 1 ? Statut::TREATED : Statut::NOT_TREATED);
            $this->addSql("UPDATE statut SET statut.state = ${state} WHERE statut.id = ${id}");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
