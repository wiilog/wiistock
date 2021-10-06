<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211006092133 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE fields_param RENAME COLUMN must_to_create TO required_create");
        $this->addSql("ALTER TABLE fields_param RENAME COLUMN must_to_modify TO required_edit");
        $this->addSql("ALTER TABLE fields_param RENAME COLUMN displayed_forms_create TO displayed_create");
        $this->addSql("ALTER TABLE fields_param RENAME COLUMN displayed_forms_edit TO displayed_edit");
    }

}
