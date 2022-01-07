<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220107102021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable("reference_article")->hasColumn("last_stock_entry")) {
            $this->addSql("ALTER TABLE reference_article ADD last_stock_entry DATETIME DEFAULT NULL");
        }
        if(!$schema->getTable("reference_article")->hasColumn("last_stock_exit")) {
            $this->addSql("ALTER TABLE reference_article ADD last_stock_exit DATETIME DEFAULT NULL");
        }
        $this->addSql("UPDATE reference_article reference_article_to_update
                            SET reference_article_to_update.last_stock_entry = (
                                SELECT stock_movement.date
                                FROM mouvement_stock stock_movement
                                WHERE type = 'entrée'
                                  AND stock_movement.ref_article_id = reference_article_to_update.id
                                ORDER BY stock_movement.date DESC,
                                         stock_movement.id DESC
                                LIMIT 1
                            )
                            WHERE reference_article_to_update.last_stock_entry IS NULL;");
        $this->addSql("UPDATE reference_article reference_article_to_update
                            SET reference_article_to_update.last_stock_exit = (
                                SELECT stock_movement.date
                                FROM mouvement_stock stock_movement
                                WHERE type = 'sortie'
                                  AND stock_movement.ref_article_id = reference_article_to_update.id
                                ORDER BY stock_movement.date DESC,
                                         stock_movement.id DESC
                                LIMIT 1
                            )
                            WHERE reference_article_to_update.last_stock_exit IS NULL;");
        $this->addSql("UPDATE reference_article reference_article_to_update
                            SET reference_article_to_update.last_stock_entry = (
                                SELECT stock_movement.date
                                FROM mouvement_stock stock_movement
                                         INNER JOIN article ON stock_movement.article_id = article.id
                                         INNER JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id AND article_fournisseur.reference_article_id = reference_article_to_update.id
                                WHERE type = 'entrée'
                                ORDER BY stock_movement.date DESC,
                                         stock_movement.id DESC
                                LIMIT 1
                            )
                            WHERE reference_article_to_update.last_stock_entry IS NULL;");
        $this->addSql("UPDATE reference_article reference_article_to_update
                            SET reference_article_to_update.last_stock_exit = (
                                SELECT stock_movement.date
                                FROM mouvement_stock stock_movement
                                         INNER JOIN article ON stock_movement.article_id = article.id
                                         INNER JOIN article_fournisseur ON article.article_fournisseur_id = article_fournisseur.id AND article_fournisseur.reference_article_id = reference_article_to_update.id
                                WHERE type = 'sortie'
                                ORDER BY stock_movement.date DESC,
                                         stock_movement.id DESC
                                LIMIT 1
                            )
                            WHERE reference_article_to_update.last_stock_exit is NULL;");

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
