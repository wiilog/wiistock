<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210430103801 extends AbstractMigration {

    public function up(Schema $schema) : void {
        if(!$schema->getTable("dashboard_component")->hasColumn("direction")) {
            $this->addSql("ALTER TABLE dashboard_component ADD direction INT NULL");
        }

        $this->addSql("UPDATE dashboard_component SET direction = 0 WHERE cell_index IS NOT NULL AND direction IS NULL");
    }

}
