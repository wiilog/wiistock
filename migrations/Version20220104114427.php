<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220104114427 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("UPDATE parametrage_global SET label = REPLACE(label, ' ', '_')");
    }

}
