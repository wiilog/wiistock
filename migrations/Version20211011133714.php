<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211011133714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dispute ADD last_history_record_id INT DEFAULT NULL');
        $this->addSql('
            UPDATE dispute
                INNER JOIN dispute_history_record ON dispute.id = dispute_history_record.dispute_id
            SET last_history_record_id = (
                SELECT id
                FROM dispute_history_record
                WHERE dispute_history_record.dispute_id = dispute.id
                ORDER BY dispute_history_record.date DESC
                LIMIT 1
            )
            WHERE 1=1
        ');
    }

    public function down(Schema $schema): void
    {
    }
}
