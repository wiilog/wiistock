<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Type;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408103807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $emergencyTypes = $this->connection->executeQuery('SELECT elements FROM fixed_field_standard WHERE entity_code = :entityCode AND field_code = :fieldCode', [
            'entityCode' => FixedFieldStandard::ENTITY_CODE_EMERGENCY,
            'fieldCode' => FixedFieldStandard::FIELD_CODE_EMERGENCY_TYPE,
        ])->fetchAllAssociative();

        $this->connection->executeQuery("INSERT INTO category_type (label) VALUES ('" . CategoryType::TRACKING_EMERGENCY . "')");
        $categoryTypeId = $this->connection->executeQuery("SELECT LAST_INSERT_ID() AS id")->fetchAllAssociative();

        if(!empty($emergencyTypes) && isset($categoryTypeId[0]["id"])){
            $emergencyTypesDecoded = json_decode($emergencyTypes[0]['elements'], true);

            foreach ($emergencyTypesDecoded ?? [] as $emergencyType) {
                $this->addSql("INSERT INTO type (category_id, label) VALUES (:categoryTypeId, :emergencyLabel)", [
                    'categoryTypeId' => $categoryTypeId[0]["id"],
                    'emergencyLabel' => $emergencyType,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
