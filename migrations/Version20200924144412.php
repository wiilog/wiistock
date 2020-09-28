<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200924144412 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $existingArrivalBusinessUnits = $this->connection
            ->executeQuery("SELECT `value` FROM parametrage_global WHERE label = 'ARRIVAL_BUSINESS_UNIT_VALUES'")
            ->fetchColumn(0) ?? json_encode([]);

        $existingDispatchBusinessUnits = $this->connection
            ->executeQuery("SELECT `value` FROM parametrage_global WHERE label = 'DISPATCH_BUSINESS_UNIT_VALUES'")
            ->fetchColumn(0) ?? json_encode([]);

        $emergencies =  $this->connection
            ->executeQuery("SELECT `value` FROM parametrage_global WHERE label = 'DISPATCH EMERGENCIES'")
            ->fetchColumn(0) ?? json_encode(["24h"]);

        $this->addSql("ALTER TABLE fields_param ADD elements JSON DEFAULT NULL");
        $this->addSql("UPDATE fields_param SET elements = '{$existingArrivalBusinessUnits}' WHERE entity_code = 'arrivage' AND field_code = 'businessUnit'");
        $this->addSql("UPDATE fields_param SET elements = '{$existingDispatchBusinessUnits}' WHERE entity_code = 'acheminements' AND field_code = 'businessUnit'");
        $this->addSql("UPDATE fields_param SET elements = '{$emergencies}' WHERE entity_code = 'acheminements' AND field_code = 'emergency'");

        $this->addSql("DELETE FROM parametrage_global WHERE label = 'ARRIVAL_BUSINESS_UNIT_VALUES'");
        $this->addSql("DELETE FROM parametrage_global WHERE label = 'DISPATCH_BUSINESS_UNIT_VALUES'");
        $this->addSql("DELETE FROM parametrage_global WHERE label = 'DISPATCH EMERGENCIES'");
        $this->addSql("DELETE FROM fields_param WHERE field_code = 'urgence'");
        $this->addSql("DELETE FROM fields_param WHERE field_code = 'business unit'");
        $this->addSql("DELETE FROM fields_param WHERE field_code = 'numéro projet'");
    }
}
