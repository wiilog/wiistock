<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220825150834 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable("translation_category"), "Reexecute migrations after fixtures");

        $natures = $this->connection->executeQuery("SELECT id, label FROM nature")->fetchAll();
        $french = $this->connection->executeQuery("SELECT id FROM language WHERE slug = 'french'")->fetchNumeric()[0] ?? null;

        $this->skipIf(!$french, "Invalid database : missing `french` language");

        if(!$schema->getTable('translation_source')->hasColumn('nature_id')) {
            $this->addSql("ALTER TABLE translation_source ADD nature_id INT");
        }

        foreach($natures as $nature) {
            $this->addSql("INSERT INTO translation_source(category_id, nature_id) VALUES (null, {$nature["id"]})");
            $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :label)", [
                "label" => $nature["label"],
            ]);
        }

    }

}
