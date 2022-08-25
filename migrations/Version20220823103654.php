<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220823103654 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->skipIf(!$schema->hasTable("translation_category"));

        $translations = $this->connection->executeQuery("SELECT * FROM previous_translation")->fetchAll();
        foreach($translations as $translation) {
            if($translation["translation"] === null || $translation["translation"] === "") {
                continue;
            }

            $query = "
                UPDATE translation
                INNER JOIN language ON translation.language_id = language.id
                SET translation = :trans
                WHERE language.slug = 'french' AND translation LIKE :label;
            ";

            $this->addSql($query, [
                "label" => $translation["label"],
                "trans" => $translation["translation"],
            ]);
        }
    }

}
