<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430095025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'put empty array in column stockEmergencyAlertModes in all line already created in Type';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('type')->hasColumn('stock_emergency_alert_modes')) {
            $this->addSql("ALTER TABLE type ADD stock_emergency_alert_modes JSON");
            $this->addSql("UPDATE type SET stock_emergency_alert_modes = '[]'");
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
