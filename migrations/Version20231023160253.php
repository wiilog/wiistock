<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231023160253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE fields_param SET displayed_create = 1, required_create = 1, displayed_edit = 1, required_edit = 1 WHERE entity_code = :entity_code AND field_code = :field_code", [
            "entity_code" => FieldsParam::ENTITY_CODE_RECEPTION,
            "field_code" => FieldsParam::FIELD_CODE_EMPLACEMENT,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
