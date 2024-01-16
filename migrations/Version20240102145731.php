<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240102145731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("RENAME TABLE transport_history TO transport_history_record");
        $this->addSql("RENAME TABLE transport_history_attachment TO transport_history_record_attachment");
        $this->addSql("ALTER TABLE transport_history_record_attachment CHANGE transport_history_id transport_history_record_id INT NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
