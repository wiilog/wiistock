<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230516100152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shipping_request_utilisateur')) {
            $this->addSql('ALTER TABLE shipping_request_utilisateur RENAME TO shipping_request_requester;');
        }
    }

    public function down(Schema $schema): void
    {
    }
}
