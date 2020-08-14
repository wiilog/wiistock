<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200805120431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove all inactive articles & references in inventory mission.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $activeStatusArticle = Article::STATUT_ACTIF;
        $disputeStatusArticle = Article::STATUT_EN_LITIGE;
        $activeStatusReference = ReferenceArticle::STATUT_ACTIF;

        $allArticle = $this->connection->executeQuery(
            "SELECT DISTINCT article.id AS id
                    FROM article
                    JOIN statut AS article_status ON article.statut_id = article_status.id
                    JOIN article_inventory_mission ON article_inventory_mission.article_id = article.id
                    JOIN article_fournisseur ON article_fournisseur.id = article.article_fournisseur_id
                    JOIN reference_article ON reference_article.id = article_fournisseur.reference_article_id
                    JOIN statut AS referenceArticle_status ON reference_article.statut_id = referenceArticle_status.id
                    LEFT JOIN inventory_entry ON article.id =  inventory_entry.article_id
                    WHERE (article_status.nom <> '${activeStatusArticle}' AND article_status.nom <> '${disputeStatusArticle}')
                       OR referenceArticle_status.nom <> '${activeStatusReference}'
                    AND inventory_entry.anomaly <> 0"
        )->fetchAll();

        foreach ($allArticle as $article) {
            $this->addSql("DELETE FROM article_inventory_mission WHERE article_inventory_mission.article_id = ${article['id']}");
        }

        $allReference = $this->connection->executeQuery(
            "SELECT DISTINCT reference_article.id AS id
                    FROM reference_article
                    JOIN inventory_mission_reference_article ON inventory_mission_reference_article.reference_article_id = reference_article.id
                    JOIN statut AS referenceArticle_status ON reference_article.statut_id = referenceArticle_status.id
                    LEFT JOIN inventory_entry ON reference_article.id =  inventory_entry.ref_article_id
                    WHERE referenceArticle_status.nom <> '${activeStatusReference}'
                    AND inventory_entry.anomaly <> 0"
        )->fetchAll();

        foreach ($allReference as $reference) {
            $referenceId = $reference['id'];
            $this->addSql("DELETE FROM inventory_mission_reference_article
                            WHERE inventory_mission_reference_article.reference_article_id = ${referenceId}");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
