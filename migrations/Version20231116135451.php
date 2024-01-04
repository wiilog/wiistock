<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Fields\FixedFieldStandard;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231116135451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("
            UPDATE fixed_field_standard
            SET required_create = 1, required_edit = 1, displayed_create = 1, displayed_edit = 1
            WHERE id = (
                SELECT id
                FROM (SELECT * FROM fixed_field_standard) AS fixed_field_standard
                WHERE entity_code = :entity_code AND field_code = :field_code
            )"
            , [
                "entity_code" => FixedFieldStandard::ENTITY_CODE_TRUCK_ARRIVAL,
                "field_code" => FixedFieldStandard::FIELD_CODE_TRUCK_ARRIVAL_CARRIER,
            ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
