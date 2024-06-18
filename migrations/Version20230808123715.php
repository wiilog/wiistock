<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\SubLineFixedField;
use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230808123715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if($_SERVER["APP_CLIENT"] === SpecificService::CLIENT_PAELLA) {

            $fields = [
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_LENGTH,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_WIDTH,
                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => SubLineFixedField::FIELD_LABEL_DISPATCH_LOGISTIC_UNIT_HEIGHT,
            ];
            foreach ($fields as $code => $label) {
                $this->addSql("
                    INSERT INTO sub_line_fields_param (entity_code, field_code, field_label, displayed)
                    VALUES (:entityCode, :fieldCode, :fieldLabel, :displayed)", [
                    'entityCode' => SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
                    'fieldCode' => $code,
                    'fieldLabel' => $label,
                    'displayed' => true,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
