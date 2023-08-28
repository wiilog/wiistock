<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230828090636 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        if(!$schema->getTable('article')->hasColumn('manufacturing_date')) {
            $this->addSql("ALTER TABLE article RENAME COLUMN manifacturing_date TO manufacturing_date");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
