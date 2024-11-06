<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241104100031 extends AbstractMigration
{
    public function getDescription(): string {
        return 'Create indexes';
    }

    public function up(Schema $schema): void {
        $this->addSql('CREATE INDEX IDX_WIILOG_LABEL ON emplacement (label)');
        $this->addSql('CREATE INDEX IDX_WIILOG_CODE ON pack (code)');
        $this->addSql('CREATE INDEX IDX_WIILOG_DATETIME ON tracking_movement (datetime)');
    }

    public function down(Schema $schema): void {}
}
