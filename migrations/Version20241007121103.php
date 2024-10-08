<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
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

        $associatedDocumentTypesAndReference = $this->connection->iterateAssociative("SELECT JSON_VALUE(reference_article.description, '$.associatedDocumentTypes') AS reference_associated_document_types,
                    reference_article.reference as reference_reference,
                    reference_article.id as reference_id
            FROM reference_article
            WHERE JSON_LENGTH(reference_article.description) > 0
            AND JSON_VALUE(reference_article.description, '$.associatedDocumentTypes') NOT IN ('[]', '')");

       foreach ($associatedDocumentTypesAndReference as $associatedDocumentTypeAndReference) {
           $referenceId = $associatedDocumentTypeAndReference['reference_id'];
           $associatedDocumentTypes = Stream::explode(',', $associatedDocumentTypeAndReference['reference_associated_document_types'])
               ->filter()
               ->toArray();

           if(!empty($associatedDocumentTypes)){
               $this->addSql("UPDATE dispatch_reference_article SET associated_document_types = :associatedDocumentTypes WHERE dispatch_reference_article.reference_article_id = :referenceId", [
                   "associatedDocumentTypes" => json_encode($associatedDocumentTypes),
                   "referenceId" => $referenceId,
               ]);
           }
       }

       $this->addSql("UPDATE reference_article
            SET description = JSON_REMOVE(reference_article.description, '$.associatedDocumentTypes')
            WHERE JSON_LENGTH(reference_article.description) > 0");
    }

    public function down(Schema $schema): void
    {

    }
}
