<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ScheduledTask\Export;
use App\Entity\Fields\FixedFieldStandard;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231127154135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        // [... "destinataire" ...]
        // => [... "destinataire" ...]
        $this->addSql("
            UPDATE export
            SET column_to_export = (
                JSON_REPLACE(
                    column_to_export,
                    JSON_UNQUOTE(JSON_SEARCH(column_to_export, 'one', 'destinataire')),
                    :field_code
                )
            )
            WHERE entity = :entity
                AND JSON_LENGTH(column_to_export) > 0
                AND JSON_SEARCH(column_to_export, 'one', 'destinataire') IS NOT NULL
        ", [
            "entity" => Export::ENTITY_ARRIVAL,
            "field_code" => FixedFieldStandard::FIELD_CODE_RECEIVERS,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
