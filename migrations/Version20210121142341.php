<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210121142341 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("UPDATE action SET label = 'modifier' WHERE label = 'modifer'");
    }

}
