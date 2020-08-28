<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200828115127 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '
        1 - Génération des dates de validation pour les acheminements traités & avec une date de validation à null. Date de validation = date de création.
        2 - Renommage date => date de création.';
    }

    public function up(Schema $schema) : void
    {

        $sqlDispatch = "SELECT acheminements.id
                        FROM acheminements
                            INNER JOIN statut s
                                ON acheminements.statut_id = s.id
                        WHERE validation_date IS NULL
                            AND s.treated = 1";

        $dispatchesWithoutValidationDateAndTreatedStatus = $this->connection->executeQuery($sqlDispatch)->fetchAll();
        foreach ($dispatchesWithoutValidationDateAndTreatedStatus as $dispatch) {
            $dispatchId = $dispatch['id'];
            $this->addSql("UPDATE acheminements SET validation_date = date WHERE id = $dispatchId");
        }

        $this->addSql('ALTER TABLE acheminements CHANGE date creation_date datetime');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
