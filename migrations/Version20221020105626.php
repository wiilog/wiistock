<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221020105626 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("UPDATE reception SET order_number = JSON_ARRAY(order_number) WHERE order_number IS NOT NULL;");
    }

}
