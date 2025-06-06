<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Type\CategoryType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231122163207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable("fixed_field_by_type")) {
            return;
        }

        // this up() migration is auto-generated, please modify it to your needs
        $locationPickFixedFieldId = $this->connection->fetchOne("SELECT id FROM fixed_field_by_type WHERE entity_code = :entity_code AND field_code = :field_code", [
            "entity_code" => FixedFieldStandard::ENTITY_CODE_DISPATCH,
            "field_code" => FixedFieldStandard::FIELD_CODE_LOCATION_PICK,
        ]);

        $locationDropFixedFieldId = $this->connection->fetchOne("SELECT id FROM fixed_field_by_type WHERE entity_code = :entity_code AND field_code = :field_code", [
            "entity_code" => FixedFieldStandard::ENTITY_CODE_DISPATCH,
            "field_code" => FixedFieldStandard::FIELD_CODE_LOCATION_DROP,
        ]);

        $categoryTypeId = $this->connection->fetchOne("SELECT id FROM category_type WHERE label = :category_type", [
            "category_type" => CategoryType::DEMANDE_DISPATCH,
        ]);
        $typesCreate = $this->connection->fetchAllAssociative("
            SELECT type.id
            FROM type
            LEFT JOIN fixed_field_by_type_required_create ON type.id = fixed_field_by_type_required_create.type_id
            WHERE category_id = :categoryTypeId
              AND fixed_field_by_type_required_create.fixed_field_by_type_id IS NULL
        ", [
            "categoryTypeId" => $categoryTypeId
        ]);
        $typesEdit = $this->connection->fetchAllAssociative("
            SELECT type.id
            FROM type
            LEFT JOIN fixed_field_by_type_required_edit ON type.id = fixed_field_by_type_required_edit.type_id
            WHERE category_id = :categoryTypeId
              AND fixed_field_by_type_required_edit.fixed_field_by_type_id IS NULL
        ", [
            "categoryTypeId" => $categoryTypeId
        ]);

        foreach ($typesCreate as $type) {
            $this->addSql("INSERT INTO fixed_field_by_type_required_create (fixed_field_by_type_id, type_id) VALUES ($locationPickFixedFieldId, {$type['id']})");
            $this->addSql("INSERT INTO fixed_field_by_type_required_create (fixed_field_by_type_id, type_id) VALUES ($locationDropFixedFieldId, {$type['id']})");
        }

        foreach ($typesEdit as $type) {
            $this->addSql("INSERT INTO fixed_field_by_type_required_edit (fixed_field_by_type_id, type_id) VALUES ($locationPickFixedFieldId, {$type['id']})");
            $this->addSql("INSERT INTO fixed_field_by_type_required_edit (fixed_field_by_type_id, type_id) VALUES ($locationDropFixedFieldId, {$type['id']})");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
