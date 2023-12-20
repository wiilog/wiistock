<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231219103036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("statut")->hasColumn("overconsumption_bill_generation_status")) {
            $this->addSql("ALTER TABLE statut ADD overconsumption_bill_generation_status BOOLEAN DEFAULT FALSE");
        }

        $oldSetting = $this->connection->executeQuery("SELECT value FROM setting WHERE label = 'DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS'")->fetchOne();

        if($oldSetting){
            $oldSetting = explode(';', $oldSetting);

            $this->addSql("UPDATE statut SET overconsumption_bill_generation_status = true WHERE statut.id = {$oldSetting[1]} AND statut.type_id = {$oldSetting[0]}");
        }
        $this->addSql("DELETE FROM setting WHERE label = 'DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS'");
    }

    public function down(Schema $schema): void
    {

    }
}
