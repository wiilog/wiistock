<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220817144425 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        if($schema->getTable("translation")->hasColumn("menu")) {
            $this->addSql("ALTER TABLE translation RENAME TO previous_translation;");
        }
    }
}
