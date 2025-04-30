<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210118153938 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE transfer_request ADD type_id INT NOT NULL;");
        $this->addSql("INSERT INTO category_type (label) VALUES ('" . CategoryType::TRANSFER_REQUEST . "')");
        $this->addSql("INSERT INTO type (category_id, label) VALUES ((SELECT LAST_INSERT_ID()), '" . Type::LABEL_STANDARD . "')");
        $this->addSql("UPDATE transfer_request SET type_id = (SELECT LAST_INSERT_ID())");
    }

}
