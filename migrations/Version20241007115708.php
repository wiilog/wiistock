<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241007115708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pack RENAME COLUMN last_tracking_id TO last_action_id');
        $this->addSql('ALTER TABLE pack RENAME COLUMN first_tracking_id TO first_action_id');
        $this->addSql('ALTER TABLE pack RENAME COLUMN last_drop_id TO last_ongoing_drop_id');
    }

    public function down(Schema $schema): void
    {
    }
}
