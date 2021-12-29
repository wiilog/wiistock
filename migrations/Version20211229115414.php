<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211229115414 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("UPDATE parametrage_global SET label = 'FONT_FAMILY' WHERE label = 'FONT FAMILY'");
    }

}
