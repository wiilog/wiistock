<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210225195933 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dashboard_meter_indicator ADD sub_counts JSON');
        $this->addSql("UPDATE dashboard_meter_indicator SET sub_counts = '[]'");
    }

    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
