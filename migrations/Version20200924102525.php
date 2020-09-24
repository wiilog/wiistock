<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200924102525 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE fields_param CHANGE displayed displayed_forms TINYINT(1)");
        $this->addSql("ALTER TABLE fields_param ADD displayed_filters TINYINT(1)");
        $this->addSql("UPDATE fields_param SET displayed_filters = 1");
    }

}
