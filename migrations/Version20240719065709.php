<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\IOT\IOTService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240719065709 extends AbstractMigration
{
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        foreach (IOTService::PROFILE_TO_MAX_TRIGGERS as $profile => $maxTriggers) {
            $this
                ->addSql("UPDATE sensor_profile SET max_triggers = :maxTriggers WHERE name = :profile", [
                    'profile' => $profile,
                    'maxTriggers' => $maxTriggers,
                ]);
        }
    }

    public function down(Schema $schema): void {}
}
