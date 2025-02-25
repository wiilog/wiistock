<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250225091820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable("pack")->hasColumn("current_tracking_delay_id")) {
            $this->addSql('ALTER TABLE pack ADD current_tracking_delay_id INT NOT NULL');
        }

        // this up() migration is auto-generated, please modify it to your needs
        $trackingDelays = $this->connection->iterateAssociative("SELECT id, pack_id FROM tracking_delay");
        foreach ($trackingDelays as $trackingDelay) {
            $this->addSql("UPDATE pack SET pack.current_tracking_delay_id = :tracking_delay_id WHERE pack.id = :pack_id", [
                'tracking_delay_id' => $trackingDelay['id'],
                'pack_id' => $trackingDelay['pack_id'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
