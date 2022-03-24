<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220324143345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable('nature')->hasColumn('allowed_forms')) {
            $this->addSql("ALTER TABLE nature ADD COLUMN allowed_forms VARCHAR(255) NOT NULL DEFAULT '{}'");
        }

        $array = json_encode(['arrival' => 'all']);
        $this->addSql("UPDATE nature SET allowed_forms = '$array' WHERE displayed = 1");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
