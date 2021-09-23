<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210907093245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renaming for return management';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ligne_article RENAME TO delivery_request_reference_line;');
        $this->addSql('ALTER TABLE delivery_request_reference_line RENAME COLUMN demande_id TO request_id;');
        $this->addSql('ALTER TABLE delivery_request_reference_line RENAME COLUMN quantite TO quantity;');
        $this->addSql('ALTER TABLE delivery_request_reference_line RENAME COLUMN quantite_prelevee TO picked_quantity;');

        $this->addSql('ALTER TABLE ligne_article_preparation RENAME TO preparation_order_reference_line;');
        $this->addSql('ALTER TABLE preparation_order_reference_line RENAME COLUMN quantite TO quantity;');
        $this->addSql('ALTER TABLE preparation_order_reference_line RENAME COLUMN quantite_prelevee TO picked_quantity;');

        $this->addSql('CREATE TABLE delivery_request_article_line (
                            id INT AUTO_INCREMENT NOT NULL,
                            article_id INT DEFAULT NULL,
                            request_id INT DEFAULT NULL,
                            quantity INT DEFAULT NULL,
                            picked_quantity INT DEFAULT NULL,
                            PRIMARY KEY(id)
                       )');
        $this->addSql('CREATE TABLE preparation_order_article_line (
                            id INT AUTO_INCREMENT NOT NULL,
                            article_id INT DEFAULT NULL,
                            preparation_id INT DEFAULT NULL,
                            quantity INT DEFAULT NULL,
                            picked_quantity INT DEFAULT NULL,
                            PRIMARY KEY(id)
                       )');

        $articles = $this->connection->iterateAssociative('
            SELECT id AS article_id,
                   demande_id,
                   quantite_aprelever,
                   quantite_prelevee,
                   quantite,
                   preparation_id AS preparation_id
            FROM article
            WHERE demande_id IS NOT NULL AND quantite_aprelever IS NOT NULL AND quantite_aprelever > 0
        ');

        foreach ($articles as $article) {
            $articleId = $article['article_id'];
            $requestId = $article['demande_id'];
            $quantity = $article['quantite_aprelever'];
            $pickedQuantity = $article['quantite_prelevee'] ?: 'NULL';
            $preparationId = $article['preparation_id'];
            $this->addSql("
                INSERT INTO delivery_request_article_line (article_id, request_id, quantity_to_pick, picked_quantity)
                VALUES (${articleId}, ${requestId}, ${quantity}, ${pickedQuantity})
            ");

            if ($preparationId) {
                $this->addSql("
                    INSERT INTO preparation_order_article_line (article_id, preparation_id, quantity_to_pick, picked_quantity)
                    VALUES (${articleId}, ${preparationId}, ${quantity}, ${pickedQuantity})
                ");
            }
        }
    }
}
