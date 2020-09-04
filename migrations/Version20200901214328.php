<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200901214328 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $defaultDisputeStatusReceptionLabel = 'DEFAULT_STATUT_LITIGE_REC';
        $defaultDisputeStatusArrivalLabel = 'DEFAULT_STATUT_LITIGE_ARR';

        $this->addSql("ALTER TABLE statut ADD default_for_category TINYINT(1) DEFAULT '0' NOT NULL;");

        $receptionDefaultStatus = $this
            ->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE label = '${defaultDisputeStatusReceptionLabel}'")
            ->fetchColumn();

        if (!empty($receptionDefaultStatus)){
            $this->addSql("UPDATE statut SET default_for_category = 1 WHERE id = ${receptionDefaultStatus}");
        }

        $arrivalDefaultStatus = $this
            ->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE label = '${defaultDisputeStatusArrivalLabel}'")
            ->fetchColumn();

        if (!empty($arrivalDefaultStatus)){
            $this->addSql("UPDATE statut SET default_for_category = 1 WHERE id = ${arrivalDefaultStatus}");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
