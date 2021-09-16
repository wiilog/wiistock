<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210915191745 extends AbstractMigration {

    public function up(Schema $schema): void {
        if(!$schema->getTable("article")->hasColumn("inactive_since")) {
            $this->addSql("ALTER TABLE article ADD inactive_since DATETIME DEFAULT NULL");
        }

        $this->connection->executeQuery(
            "UPDATE
                    article
                        INNER JOIN statut s on article.statut_id = s.id,
                    (SELECT article_id, date
                     FROM mouvement_stock
                     WHERE article_id IS NOT NULL
                       AND mouvement_stock.id IN (
                         SELECT MAX(mouvement_stock.id)
                         FROM mouvement_stock
                         WHERE date IS NOT NULL
                           AND type = 'sortie'
                         GROUP BY article_id)
                     ORDER BY article_id) dates
                SET inactive_since = dates.date
                WHERE article.id = dates.article_id
                  AND s.code = 'consommé'"
        );
    }

}
