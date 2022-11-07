<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221026095653 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $sources = $this->connection->executeQuery(
            "SELECT s.id FROM translation_source s INNER JOIN translation t on s.id = t.source_id WHERE t.translation IN (:translations)",
            [
                "translations" => [
                    "prise dans UL",
                    "Prise dans UL",
                    "dépose dans UL",
                    "Dépose dans UL",
                    "depose dans UL",
                    "Depose dans UL",
                ],
            ],
            [
                "translations" => Connection::PARAM_STR_ARRAY,
            ]
        )->fetchAll();

        foreach ($sources as $source) {
            $this->addSql("DELETE FROM translation WHERE source_id = {$source["id"]}");
            $this->addSql("DELETE FROM translation_source WHERE id = {$source["id"]}");
        }
    }

}
