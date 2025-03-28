<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250319143004 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE article ADD last_movement_id INT DEFAULT NULL');
        $this->addSql('
            UPDATE article
            SET last_movement_id = (
                SELECT id
                FROM mouvement_stock
                WHERE article.id = mouvement_stock.article_id
                ORDER BY mouvement_stock.date DESC LIMIT 1
            )
        ');

        $this->addSql('ALTER TABLE reference_article ADD last_movement_id INT DEFAULT NULL');
        $this->addSql('
            UPDATE reference_article
            SET last_movement_id = (
                SELECT id
                FROM mouvement_stock
                WHERE reference_article.id = mouvement_stock.ref_article_id
                ORDER BY mouvement_stock.date DESC LIMIT 1
            )
        ');
    }

    public function down(Schema $schema): void {}
}
