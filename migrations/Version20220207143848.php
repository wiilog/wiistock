<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220207143848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable("utilisateur")->hasColumn("location_dropzone_id")) {
            $this->addSql("ALTER TABLE utilisateur RENAME COLUMN dropzone_id TO location_dropzone_id");
        }

        if(!$schema->getTable("utilisateur")->hasColumn("label")) {
            $this->addSql("ALTER TABLE location_group RENAME COLUMN name TO label");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
