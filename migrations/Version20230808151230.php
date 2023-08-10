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
        // this up() migration is auto-generated, please modify it to your needs

        if(!$schema->getTable('fields_param')->hasColumn('elements_type')) {
            $this->addSql("ALTER TABLE fields_param RENAME COLUMN modal_type TO elements_type");
        }

        if(!$schema->getTable('sub_line_fields_param')->hasColumn('elements_type')) {
            $this->addSql("ALTER TABLE sub_line_fields_param ADD COLUMN elements_type VARCHAR(255) NULL");
        }

        $subLineFields = [
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH,
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH,
            SubLineFieldsParam::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT,
        ];

        foreach ($subLineFields as $subLineField) {
            $this->addSql("
                UPDATE sub_line_fields_param
                SET elements_type = :elementsType
                WHERE field_code = :fieldCode
                  AND entity_code = :entityCode", [
                    'elementsType' => FieldsParam::ELEMENTS_TYPE_FREE_NUMBER,
                    'fieldCode' => $subLineField,
                    'entityCode' => SubLineFieldsParam::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
