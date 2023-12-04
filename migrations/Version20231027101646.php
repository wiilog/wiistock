<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\FixedFieldStandard;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231027101646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE fixed_field_standard SET displayed_create = 1, required_create = 1, displayed_edit = 1, required_edit = 1 WHERE entity_code = :entity_code AND field_code = :field_code", [
            "entity_code" => FixedFieldStandard::ENTITY_CODE_RECEPTION,
            "field_code" => FixedFieldStandard::FIELD_CODE_EMPLACEMENT,
        ]);
    }

    public function down(Schema $schema): void
    {
    }
}
