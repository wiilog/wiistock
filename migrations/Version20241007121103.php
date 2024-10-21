<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\NullLogger;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241007121103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("dispatch_reference_article")->hasColumn("associated_document_types")) {
            $this->addSql("ALTER TABLE dispatch_reference_article ADD COLUMN associated_document_types JSON DEFAULT NULL");
        }

        self::migrate($this, 5000);


    }

    public function down(Schema $schema): void
    {

    }

    public static function migrate(AbstractMigration $migration, int $limit = null): void
    {
        $migration->connection->getConfiguration()->setMiddlewares([new Middleware(new NullLogger())]);

        $queryLimit = $limit ? "LIMIT $limit" : "";
        $associatedDocumentTypesAndReference = $migration->connection->iterateAssociative("
            SELECT JSON_VALUE(reference_article.description, '$.associatedDocumentTypes') AS reference_associated_document_types,
                    reference_article.reference as reference_reference,
                    reference_article.id as reference_id
            FROM reference_article
            WHERE JSON_LENGTH(reference_article.description) > 0
            AND JSON_VALUE(reference_article.description, '$.associatedDocumentTypes') NOT IN ('[]', '')
            {$queryLimit}");

        foreach ($associatedDocumentTypesAndReference as $associatedDocumentTypeAndReference) {
            $referenceId = $associatedDocumentTypeAndReference['reference_id'];
            $associatedDocumentTypes = Stream::explode(',', $associatedDocumentTypeAndReference['reference_associated_document_types'])
                ->filter()
                ->toArray();

            if(!empty($associatedDocumentTypes)){
                $migration->addSql("UPDATE dispatch_reference_article SET associated_document_types = :associatedDocumentTypes WHERE dispatch_reference_article.reference_article_id = :referenceId", [
                    "associatedDocumentTypes" => json_encode($associatedDocumentTypes),
                    "referenceId" => $referenceId,
                ]);
            }
        }
    }
}
