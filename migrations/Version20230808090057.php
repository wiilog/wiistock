<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use App\Service\SpecificService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230808090057 extends AbstractMigration
{

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if($_SERVER["APP_CLIENT"] === SpecificService::CLIENT_AIA_CUERS) {

            $fields = [
                FieldsParam::FIELD_CODE_CUSTOMER_NAME_DISPATCH => FieldsParam::FIELD_LABEL_CUSTOMER_NAME_DISPATCH,
                FieldsParam::FIELD_CODE_CUSTOMER_PHONE_DISPATCH => FieldsParam::FIELD_LABEL_CUSTOMER_PHONE_DISPATCH,
                FieldsParam::FIELD_CODE_CUSTOMER_RECIPIENT_DISPATCH => FieldsParam::FIELD_LABEL_CUSTOMER_RECIPIENT_DISPATCH,
                FieldsParam::FIELD_CODE_CUSTOMER_ADDRESS_DISPATCH => FieldsParam::FIELD_LABEL_CUSTOMER_ADDRESS_DISPATCH,
            ];
            foreach ($fields as $code => $label) {
                $this->addSql("
                    INSERT INTO fields_param (entity_code, field_code, field_label, displayed_create, displayed_edit, displayed_filters)
                    VALUES (:entityCode, :fieldCode, :fieldLabel, :displayedCreate, :displayedEdit, :displayedFilters)", [
                        'entityCode' => FieldsParam::ENTITY_CODE_DISPATCH,
                        'fieldCode' => $code,
                        'fieldLabel' => $label,
                        'displayedCreate' => true,
                        'displayedEdit' => true,
                        'displayedFilters' => false,
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
