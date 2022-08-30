<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220826170100 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable("translation_category") || !$schema->hasTable("translation"), "Reexecute migrations after fixtures");

        $statuses = $this->connection->executeQuery("SELECT id, nom FROM statut")->fetchAll();
        $french = $this->connection->executeQuery("SELECT id FROM language WHERE slug = 'french'")->fetchNumeric()[0] ?? null;

        $this->skipIf(!$french, "Invalid database : missing `french` language");

        if(!$schema->getTable('translation_source')->hasColumn('status_id')) {
            $this->addSql("ALTER TABLE translation_source ADD status_id INT");
        }

        foreach($statuses as $status) {
            $this->addSql("INSERT INTO translation_source(category_id, status_id) VALUES (null, {$status["id"]})");
            $this->addSql("INSERT INTO translation(language_id, source_id, translation) VALUES ($french, (SELECT LAST_INSERT_ID()), :nom)", [
                "nom" => $status["nom"],
            ]);
        }

    }

}
