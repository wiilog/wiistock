<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231024130104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename table "fields_param" to "fixed_field_standard"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE fields_param TO fixed_field_standard');
    }

    public function down(Schema $schema): void
    {
    }
}
