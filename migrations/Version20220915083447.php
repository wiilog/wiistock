<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220915083447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable("translation_category"), "Reexecute migrations after fixtures");

        $types = $this->connection->executeQuery("SELECT type.id AS id, type.label AS label FROM type LEFT JOIN translation_source ON type.id = translation_source.type_id WHERE translation_source.id IS NULL")->fetchAll();
        $french = $this->connection->executeQuery("SELECT id FROM language WHERE slug = 'french'")->fetchNumeric()[0] ?? null;

        $this->skipIf(!$french, "Invalid database : missing `french` language");

        foreach($types as $type) {
            $this->addSql("INSERT INTO translation_source(category_id, type_id) VALUES (null, {$type["id"]})");
            $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :label)", [
                "label" => $type["label"],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
