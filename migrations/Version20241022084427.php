<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241022084427 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $fieldsToFill = [
            "required_create" => "fixed_field_by_type_required_create",
            "required_edit" => "fixed_field_by_type_required_edit",
            "displayed_create" => "fixed_field_by_type_displayed_create",
            "displayed_edit" => "fixed_field_by_type_displayed_edit",
            "kept_in_memory" => "fixed_field_by_type_kept_in_memory",
        ];

        $onFilerFields = [];

        // security in case the table does not exist (never happened normally)
        if (!$schema->hasTable('fixed_field_by_type')) {
            return;
        }

        // find the category production
        $category = $this->connection->fetchAssociative('SELECT id FROM category_type WHERE label = "production"', []);

        // find all the types id for the category production
        $types = $this->connection->fetchAllAssociative('SELECT id FROM type WHERE category_id = :category_id',
            [
                'category_id' => $category['id']
            ]
        );

        // find all the fixed fields standard for the entity code production
        $fieldsStandards = $this->connection->fetchAllAssociative('SELECT * FROM fixed_field_standard WHERE entity_code = "production"');

        foreach ($fieldsStandards as $fieldsStandard) {
            // create a new fixed field by type for each fixed field standard
            $this->addSql('INSERT INTO fixed_field_by_type (entity_code, field_code, field_label, elements, elements_type) VALUES (:entity_code, :field_code, :field_label, :elements, :elements_type)',
                [
                    'entity_code' => $fieldsStandard['entity_code'],
                    'field_code' => $fieldsStandard['field_code'],
                    'field_label' => $fieldsStandard['field_label'],
                    'elements' => $fieldsStandard['elements'],
                    'elements_type' => $fieldsStandard['elements_type'],
                ]
            );

            if ($fieldsStandard['displayed_filters']) {
                $onFilerFields[] = $fieldsStandard['field_code'];
            }

            // create the lines in association table for each type
            foreach ($fieldsToFill as $fieldToFill => $table) {
                if ($fieldsStandard[$fieldToFill]) {
                    foreach ($types as $type) {
                        $this->addSql('
                                INSERT INTO ' . $table . ' (fixed_field_by_type_id, type_id) VALUES (
                                    (SELECT id FROM fixed_field_by_type WHERE entity_code = :entity_code AND field_code = :field_code),
                                    :type_id
                                )
                            ',
                            [
                                'type_id' => $type['id'],
                                'entity_code' => $fieldsStandard['entity_code'],
                                'field_code' => $fieldsStandard['field_code'],
                            ]
                        );
                    }
                }
            }

            // delete the fixed field standard
            $this->addSql('DELETE FROM fixed_field_standard WHERE id = :id', ['id' => $fieldsStandard['id']]);
        }

        $this->addSql("INSERT INTO setting (label, value) VALUES (':label', ':value')", [
            ":label" => Setting::PRODUCTION_FIXED_FIELDS_ON_FILTERS,
            ":value" => join(',', $onFilerFields),
        ]);
    }

    public function down(Schema $schema): void {}
}
