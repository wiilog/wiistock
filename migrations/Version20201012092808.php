<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201012092808 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {


        // this up() migration is auto-generated, please modify it to your needs
        $this
            ->addSql('ALTER TABLE piece_jointe ADD COLUMN full_path VARCHAR(255) DEFAULT NULL');
        $this
            ->addSql("UPDATE piece_jointe SET full_path = CONCAT('/uploads/attachements/', file_name)");
        $this
            ->addSql('ALTER TABLE piece_jointe RENAME attachment');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
