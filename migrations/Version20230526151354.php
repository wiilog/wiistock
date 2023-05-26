<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230526151354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // rename into FieldsParam::FIELD_CODE_EMERGENCY_TYPE the the filed param where entity_code = FieldsParam::ENTITY_CODE_EMERGENCY and field code = "type"
        $this->addSql('UPDATE fields_param SET field_code = :newCode WHERE entity_code = :entiyCode AND field_code = :oldCode', [
            'newCode' => FieldsParam::FIELD_CODE_EMERGENCY_TYPE,
            'entiyCode' => FieldsParam::ENTITY_CODE_EMERGENCY,
            'oldCode' => 'type'
        ]);
    }

    public function down(Schema $schema): void
    {

    }
}
