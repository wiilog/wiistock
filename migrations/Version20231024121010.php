<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\FieldsParam;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231024121010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("CREATE TABLE arrival_receiver(arrival_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(arrival_id, user_id))");
        $this->addSql("INSERT INTO arrival_receiver (arrival_id, user_id)
                               SELECT id AS arrival_id, destinataire_id AS user_id
                                    FROM arrivage
                                    WHERE destinataire_id IS NOT NULL
        ");

        $this->addSql("UPDATE fields_param SET field_code = :field_code, field_label = :field_label WHERE entity_code = :entity_code AND field_code = 'destinataire'", [
            "field_code" => FieldsParam::FIELD_CODE_RECEIVERS,
            "field_label" => FieldsParam::FIELD_LABEL_RECEIVERS,
            "entity_code" => FieldsParam::ENTITY_CODE_ARRIVAGE,
        ]);

        $this->addSql("UPDATE kept_field_value SET field = :field_code WHERE field = 'destinataire' AND entity = :entity_code", [
            "field_code" => FieldsParam::FIELD_CODE_RECEIVERS,
            "entity_code" => FieldsParam::ENTITY_CODE_ARRIVAGE,
        ]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
