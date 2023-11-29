<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
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
        $types = $this->connection->fetchAllAssociative("SELECT id FROM type WHERE category_id = $categoryTypeId");

        foreach ($types as $type) {
            $this->addSql("INSERT INTO fixed_field_by_type_required_create (fixed_field_by_type_id, type_id) VALUES ($locationPickFixedFieldId, {$type['id']})");
            $this->addSql("INSERT INTO fixed_field_by_type_required_create (fixed_field_by_type_id, type_id) VALUES ($locationDropFixedFieldId, {$type['id']})");

            $this->addSql("INSERT INTO fixed_field_by_type_required_edit (fixed_field_by_type_id, type_id) VALUES ($locationPickFixedFieldId, {$type['id']})");
            $this->addSql("INSERT INTO fixed_field_by_type_required_edit (fixed_field_by_type_id, type_id) VALUES ($locationDropFixedFieldId, {$type['id']})");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
