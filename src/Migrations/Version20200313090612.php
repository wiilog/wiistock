<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200313090612 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("
                            UPDATE reference_article AS ra
                            SET quantite_stock =
                            (
                                SELECT SUM(a.quantite)
                                FROM article a
                                INNER JOIN article_fournisseur af on a.article_fournisseur_id = af.id
                                INNER JOIN statut s on a.statut_id = s.id
                                WHERE s.nom = 'disponible' AND af.reference_article_id = ra.id
                            )
                            WHERE ra.type_quantite = 'article'");
        $this->addSql("
                            UPDATE reference_article AS ra
                            SET quantite_reservee =
                            (
                                SELECT SUM(l.quantite)
                                FROM  ligne_article_preparation l
                                INNER JOIN preparation p on l.preparation_id = p.id
                                INNER JOIN statut s on p.statut_id = s.id
                                WHERE (s.nom = 'à traiter' OR s.nom = 'en cours de préparation') AND l.reference_id = ra.id
                            )");
        $this->addSql("
                            UPDATE reference_article AS ra
                            SET ra.quantite_disponible = ra.quantite_stock - ra.quantite_reservee
                            ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
