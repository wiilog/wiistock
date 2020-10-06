<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201002092416 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $dispatchPickField = FieldsParam::FIELD_CODE_LOCATION_PICK;
        $dispatchDropField = FieldsParam::FIELD_CODE_LOCATION_DROP;
        $dispatchCode = FieldsParam::ENTITY_CODE_DISPATCH;

        $this
            ->addSql("
                    UPDATE fields_param
                    SET field_required_hidden = 1, must_to_create = 1, must_to_modify = 1
                    WHERE (field_code = '${dispatchPickField}' OR field_code = '${dispatchDropField}')
                    AND entity_code = '${dispatchCode}'
            ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
