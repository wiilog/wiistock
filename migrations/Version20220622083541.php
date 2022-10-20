<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220622083541 extends AbstractMigration
{

    const SETTINGS_NOTIFICATIONS = 'afficher modèles de notifications';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE action SET label = :newLabel WHERE label = :oldLabel", [
            "newLabel" => self::SETTINGS_NOTIFICATIONS,
            "oldLabel" => "afficher notifications"
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
