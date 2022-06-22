<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220622101626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable('inventory_mission')->hasColumn('name')) {
            $this->addSql("ALTER TABLE inventory_mission ADD name VARCHAR(255)");
        }
        $this->addSql("
            UPDATE inventory_mission
            SET name = CONCAT('mission_du_', DATE_FORMAT(start_prev_date, '%d-%m-%Y'))
            WHERE name IS NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
