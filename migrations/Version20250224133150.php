<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224133150 extends AbstractMigration {
    public function getDescription(): string {
        return 'add last_sleeping_stock_alert_answer in reference_article';
    }

    public function up(Schema $schema): void {
        if (!$schema->getTable('reference_article')->hasColumn('last_sleeping_stock_alert_answer')) {
            $this->addSql('ALTER TABLE reference_article ADD last_sleeping_stock_alert_answer DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void {
        if ($schema->getTable('reference_article')->hasColumn('last_sleeping_stock_alert_answer')) {
            $this->addSql('ALTER TABLE reference_article DROP last_sleeping_stock_alert_answer');
        }
    }
}
