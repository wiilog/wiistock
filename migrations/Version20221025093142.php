<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221025093142 extends AbstractMigration
{


    public function getDescription(): string
    {
        return '';
    }

    /**
     * Same as Version20220930120407 for all free field categories
     */
    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable("translation_category"), "Reexecute migrations after fixtures");

        $french = $this->connection->executeQuery("SELECT id FROM language WHERE slug = 'french'")->fetchNumeric()[0] ?? null;
        $this->skipIf(!$french, "Invalid database: missing `french` language");

        $freeFields = $this->connection
            ->executeQuery("
                SELECT free_field.id, free_field.label, free_field.default_value, free_field.elements
                FROM free_field
                    LEFT JOIN translation_source on free_field.id = translation_source.free_field_id
                WHERE translation_source.id IS NULL
            ")
            ->fetchAll();

        if (!$schema->getTable('translation_source')->hasColumn('free_field_default_value_id')) {
            $this->addSql('ALTER TABLE translation_source ADD free_field_default_value_id INT DEFAULT NULL;');
        }
        foreach($freeFields as $freeField) {
            $this->addSql("INSERT INTO translation_source(category_id, free_field_id) VALUES (null, {$freeField["id"]})");
            $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :label)", [
                "label" => $freeField["label"],
            ]);

            if($freeField["default_value"]) {
                $this->addSql("INSERT INTO translation_source(category_id, free_field_default_value_id) VALUES (null, {$freeField["id"]})");
                $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :label)", [
                    "label" => $freeField["default_value"],
                ]);
            }

            if($freeField["elements"]) {
                $elements = Stream::from(json_decode($freeField["elements"])
                    ?: explode(";", $freeField["elements"])
                        ?: [])
                    ->filter()
                    ->map(fn($e) => trim($e))
                    ->toArray();
                foreach ($elements as $element) {
                    if(!$element) {
                        continue;
                    }

                    $this->addSql("INSERT INTO translation_source(category_id, element_of_free_field_id) VALUES (null, {$freeField["id"]})");
                    $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :label)", [
                        "label" => $element,
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {

    }
}
