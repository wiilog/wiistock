<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230607093748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable("dispatch")->hasColumn("updated_at")) {
            $this->addSql('ALTER TABLE dispatch ADD updated_at');
        }

        $this->addSql('UPDATE dispatch SET updated_at = NOW() WHERE updated_at IS NULL');
    }

    public function down(Schema $schema): void
    {

    }
}
