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
        return 'put empty array in colum emergencyStockWarning in all line already created in Type';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('type')->hasColumn('emergency_stock_warnings')) {
            $this->addSql("UPDATE type SET emergency_stock_warnings = '[]'");
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
