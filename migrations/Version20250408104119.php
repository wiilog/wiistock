<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Setting;
use App\Entity\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408104119 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    public function getDescription(): string
    {
        return 'Creation du parametrage des champs fixes par type pour les urgences traces en fonction du parametrage des champs fixes standards';
    }

    public function up(Schema $schema): void
    {
        $fieldsToFill = [
            "required_create" => "fixed_field_by_type_required_create",
            "required_edit" => "fixed_field_by_type_required_edit",
            "displayed_create" => "fixed_field_by_type_displayed_create",
            "displayed_edit" => "fixed_field_by_type_displayed_edit",
            "kept_in_memory" => "fixed_field_by_type_kept_in_memory",
        ];

        // security in case the table does not exist (never happened normally)
        if (!$schema->hasTable('fixed_field_by_type')) {
            return;
        }

        // find the category production
        $category = $this->connection->fetchAssociative('SELECT category_type.id FROM category_type WHERE category_type.label = :categoryTypeLabel LIMIT 1', [
            "categoryTypeLabel" => CategoryType::TRACKING_EMERGENCY,
        ]);

        // find all the types id for the category production
        $types = $category
            ? $this->connection->fetchAllAssociative('SELECT id FROM type WHERE category_id = :category_id',
                [
                    'category_id' => $category['id']
                ])
            : [];

        // find all the fixed fields standard for the entity code production
        $fieldsStandards = $this->connection->fetchAllAssociative('SELECT * FROM fixed_field_standard WHERE entity_code = :entityCode', [
            "entityCode" => FixedFieldStandard::ENTITY_CODE_EMERGENCY,
        ]);

        foreach ($fieldsStandards as $fieldsStandard) {
            // create a new fixed field by type for each fixed field standard
            $this->addSql('INSERT INTO fixed_field_by_type (entity_code, field_code, field_label, elements, elements_type) VALUES (:entity_code, :field_code, :field_label, :elements, :elements_type)',
                [
                    'entity_code' => FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY,
                    'field_code' => $fieldsStandard['field_code'],
                    'field_label' => $fieldsStandard['field_label'],
                    'elements' => $fieldsStandard['elements'],
                    'elements_type' => $fieldsStandard['elements_type'],
                ]
            );

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
                                'entity_code' => FixedFieldStandard::ENTITY_CODE_TRACKING_EMERGENCY,
                                'field_code' => $fieldsStandard['field_code'],
                            ]
                        );
                    }
                }
            }

            // delete the fixed field standard
            $this->addSql('DELETE FROM fixed_field_standard WHERE id = :id', ['id' => $fieldsStandard['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
