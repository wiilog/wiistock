<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221026090522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE reception_line (
                id INT AUTO_INCREMENT NOT NULL,
                reception_id INT DEFAULT NULL,
                pack_id INT DEFAULT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $alreadyAddedReceptions = [];

        $receptionReferenceArticles = $this->connection
            ->executeQuery("
                SELECT *
                FROM reception_reference_article
            ")
            ->fetchAll();
        $this->addSql('ALTER TABLE reception_reference_article ADD reception_line_id INT DEFAULT NULL');
        foreach ($receptionReferenceArticles as $receptionReferenceArticle) {
            $receptionId = $receptionReferenceArticle['reception_id'];
            $receptionReferenceArticleId = $receptionReferenceArticle['id'];
            if (!in_array($receptionId, $alreadyAddedReceptions)) {
                $this->addSql("INSERT INTO reception_line(reception_id, pack_id) VALUES (:receptionId, NULL)", [
                    'receptionId' => $receptionId
                ]);
                $alreadyAddedReceptions[] = $receptionId;
            }
            $this->addSql("
                UPDATE reception_reference_article
                SET reception_reference_article.reception_line_id = (
                    SELECT reception_line.id
                    FROM reception_line
                    WHERE reception_line.reception_id = :receptionId
                    LIMIT 1
                )
                WHERE reception_reference_article.id = :receptionReferenceArticleId
            ", [
                'receptionId' => $receptionId,
                'receptionReferenceArticleId' => $receptionReferenceArticleId
            ]);
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
