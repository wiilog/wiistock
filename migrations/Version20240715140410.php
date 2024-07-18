<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Language;
use App\Entity\Translation;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240715140410 extends AbstractMigration
{
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $duplicates = $this->connection->executeQuery(
            "SELECT source.id AS  sourceId, translation.translation as translation
            FROM translation
                     INNER JOIN translation_source source ON translation.source_id = source.id
                     INNER JOIN language ON translation.language_id = language.id
            WHERE language.slug IN ('french-default')
            AND (
                SELECT COUNT(subTranslation.id)
                FROM translation AS subTranslation
                INNER JOIN translation_source AS subSource ON subTranslation.source_id = subSource.id
                WHERE BINARY subTranslation.translation = BINARY translation.translation
                AND subTranslation.language_id = translation.language_id
                AND subSource.category_id = source.category_id
            ) > 1;
            ",
        )->fetchAllAssociative();

        $cleaned = [];

        foreach ($duplicates as $duplicate) {
            $translation = $duplicate['translation'];
            $sourceIds = $duplicate['sourceId'];
            if (!in_array($translation, $cleaned)) {
                $cleaned[] = $translation;
            }
            else {
                $this->addSql("DELETE FROM translation WHERE source_id = :sourceId", ["sourceId" => $sourceIds]);
                $this->addSql("DELETE FROM translation_source WHERE id = :sourceId", ["sourceId" => $sourceIds]);
            }
        }
    }

    public function down(Schema $schema): void {}
}
