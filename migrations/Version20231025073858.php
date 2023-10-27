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
        return 'Rename table "sub_line_fields_param to "sub_line_fixed_field" and create new tables for fixed fields by type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE sub_line_fields_param TO sub_line_fixed_field');

        $this->addSql('CREATE TABLE fixed_field_by_type (
            id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
            entity_code VARCHAR(255) NOT NULL,
            field_code VARCHAR(255) NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            elements LONGTEXT,
            elements_type VARCHAR(255) DEFAULT NULL
        )');

        $this->addSql("CREATE TABLE fixed_field_by_type_required_create (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_required_edit (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_kept_in_memory (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_displayed_create (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_displayed_edit (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_displayed_filters (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_on_mobile (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");

        $this->addSql("CREATE TABLE fixed_field_by_type_on_label (
            fixed_field_by_type_id INT NOT NULL,
            type_id INT NOT NULL,
            PRIMARY KEY(fixed_field_by_type_id, type_id)
        )");
    }

    public function down(Schema $schema): void
    {
    }
}
