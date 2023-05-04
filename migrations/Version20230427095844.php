<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use App\Entity\Setting;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Google\Service\Spanner\Field;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230427095844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $fieldCode = FieldsParam::FIELD_CODE_DESTINATION_DEMANDE;
        $oldSetting = $this->connection->executeQuery("
            SELECT setting.value
            FROM setting
            WHERE setting.label = '" . Setting::DEFAULT_LOCATION_LIVRAISON . "'
        ")->fetchOne();

        $newFieldsParam = $this->connection->executeQuery("
            SELECT *
            FROM fields_param
            WHERE fields_param.field_code = :fieldCode
        ", [
            'fieldCode' => $fieldCode,
        ])->fetchOne();

        if(!$newFieldsParam){
            $this->addSql("
                INSERT INTO fields_param(entity_code, field_code, field_label, required_create, required_edit, displayed_create, displayed_edit, displayed_filters, modal_type)
                VALUES (:entityCode, :fieldCode, :fieldLabel, '1', '1', '1', '1', '1', :modalType)
            ", [
                'entityCode' => FieldsParam::ENTITY_CODE_DEMANDE,
                'fieldCode' => $fieldCode,
                'fieldLabel' => FieldsParam::FIELD_LABEL_DESTINATION_DEMANDE,
                'modalType' => FieldsParam::MODAL_LOCATION_BY_TYPE,
            ]);
        }

        $this->addSql("
            UPDATE fields_param
            SET elements = '$oldSetting'
            WHERE fields_param.field_code = :fieldCode", [
                'fieldCode' => $fieldCode,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
