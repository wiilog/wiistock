<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200924131807 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE champ_libre ADD displayed_create TINYINT(1)");
        $this->addSql("UPDATE champ_libre SET displayed_create = 1");
    }

}
