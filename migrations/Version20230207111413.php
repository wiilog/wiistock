<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230207111413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable('inventory_mission')->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE inventory_mission ADD COLUMN created_at DATE');
        }
        $this->addSql('UPDATE inventory_mission SET created_at = start_prev_date');
    }
}
