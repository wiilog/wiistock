<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200924153539 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE fields_param CHANGE displayed_forms displayed_forms_create TINYINT(1)");
        $this->addSql("ALTER TABLE fields_param ADD displayed_forms_edit TINYINT(1)");
        $this->addSql("UPDATE fields_param SET displayed_forms_edit = displayed_forms_create");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
