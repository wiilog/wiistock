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
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE preparation DROP demande_id');
    }
}
