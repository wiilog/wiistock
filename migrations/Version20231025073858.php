<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231025073858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename table "sub_line_fields_param to "sub_line_fixed_field"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE sub_line_fields_param TO sub_line_fixed_field');
    }

    public function down(Schema $schema): void
    {
    }
}
