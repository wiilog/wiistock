<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200129141840 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE preparation ADD demande_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD preparation_id INT DEFAULT NULL');
        $this->addSql('CREATE TABLE ligne_article_preparation (id INT AUTO_INCREMENT NOT NULL, reference_id INT NOT NULL, preparation_id INT NOT NULL, quantite INT NOT NULL, quantite_prelevee INT DEFAULT NULL, to_split TINYINT(1) DEFAULT NULL, INDEX IDX_B87D82D01645DEA9 (reference_id), INDEX IDX_B87D82D03DD9B8BA (preparation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('UPDATE preparation SET demande_id = (
                            SELECT demande.id
                            FROM demande as demande
                            WHERE demande.preparation_id = preparation.id
                    )');
        $this->addSql('
            DELETE l
            FROM livraison AS l
            LEFT OUTER JOIN
            (
                 SELECT
                 MAX(livraison.id) as IdToKeep
                 FROM
                 (SELECT * FROM livraison) AS livraison
                INNER JOIN preparation ON preparation.id = livraison.preparation_id
                 GROUP BY preparation.id
            ) AS tableToKeep ON tableToKeep.IdToKeep = l.id
            WHERE tableToKeep.IdToKeep IS NULL
        ');
        $this->addSql("
            DELETE p
            FROM preparation AS p
            LEFT JOIN demande ON demande.preparation_id = p.id
            WHERE demande.id IS NULL
        ");
        $this->addSql("
            DELETE FROM filtre_sup
        ");
        $this->addSql("
            UPDATE article SET preparation_id =
            (
                SELECT preparation.id
                FROM preparation
                WHERE preparation.demande_id = article.demande_id
            )
            WHERE demande_id IS NOT NULL AND preparation_id IS NULL
        ");
        $this->addSql("
            INSERT INTO `ligne_article_preparation` (reference_id, preparation_id, quantite, quantite_prelevee, to_split)
            SELECT reference_id, preparation.id as preparation_id, quantite, quantite_prelevee, to_split
            FROM ligne_article
            INNER JOIN demande ON demande.id = ligne_article.demande_id
            INNER JOIN preparation ON demande.id = preparation.demande_id
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE preparation DROP demande_id');
    }
}
