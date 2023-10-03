<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use App\Entity\SubLineFieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230808151230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is made by thomas, please modify it to your needs
        if (!$schema->getTable('fields_param')->hasColumn('elements_type')) {
            $this->addSql("ALTER TABLE fields_param RENAME COLUMN modal_type TO elements_type");
        }

        if ($schema->hasTable('sub_line_fields_param') && !$schema->getTable('sub_line_fields_param')->hasColumn('elements_type')) {
            $this->addSql("ALTER TABLE sub_line_fields_param ADD COLUMN elements_type VARCHAR(255) NULL");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
