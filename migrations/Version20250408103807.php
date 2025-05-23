<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408103807 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $emergencyTypeValues = $this->connection
            ->executeQuery('
                SELECT elements
                FROM fixed_field_standard
                WHERE entity_code = :entityCode AND field_code = :fieldCode
            ', [
                'entityCode' => FixedFieldStandard::ENTITY_CODE_EMERGENCY,
                'fieldCode' => FixedFieldStandard::FIELD_CODE_EMERGENCY_TYPE,
            ])
            ->fetchAllAssociative();

        $this->addSql("INSERT INTO category_type (label) VALUES (:categoryTypeLabel)", [
            'categoryTypeLabel' => CategoryType::TRACKING_EMERGENCY
        ]);

        $emergencyTypeValuesDecoded = @json_decode($emergencyTypeValues[0]['elements'], true);
        $emergencyTypeValuesDecoded[]= Type::LABEL_STANDARD;

        $emergencyTypes = $this->connection
            ->executeQuery('
                SELECT DISTINCT type
                FROM urgence
            ', [])
            ->fetchAllAssociative();

        $emergencyTypes = Stream::from($emergencyTypes)
            ->map(fn(array $emergencyType) => $emergencyType['type'])
            ->toArray();

        $emergencyTypeValuesDecoded = Stream::from($emergencyTypes, $emergencyTypeValuesDecoded)
            ->unique()
            ->filter()
            ->toArray();

        foreach ($emergencyTypeValuesDecoded as $emergencyTypeValue) {
            $this->addSql("
                INSERT INTO type (category_id, color, label)
                VALUES (
                    (
                        SELECT category_type.id
                        FROM category_type
                        WHERE category_type.label = :categoryTypeLabel
                        LIMIT 1
                    ),
                    :color,
                    :emergencyLabel
                )",
                [
                    'categoryTypeLabel' => CategoryType::TRACKING_EMERGENCY,
                    'color' => Type::DEFAULT_COLOR,
                    'emergencyLabel' => $emergencyTypeValue,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
