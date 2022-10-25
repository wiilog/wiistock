<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221019141245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE fields_param SET modal_type = 'FREE' WHERE elements IS NOT NULL");
        $this->addSql("UPDATE fields_param SET modal_type = 'USER_BY_TYPE' WHERE field_code = 'receivers'");
    }

    public function down(Schema $schema): void
    {
    }
}
