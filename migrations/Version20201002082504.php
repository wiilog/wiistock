<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201002082504 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql("RENAME TABLE champ_libre TO free_field");
    }

}
