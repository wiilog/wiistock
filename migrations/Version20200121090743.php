<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200121090743 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE reference_article ADD date_emergency_triggered DATETIME DEFAULT NULL');
        $this->addSql("
            UPDATE reference_article ra
            SET date_emergency_triggered = NOW()
            WHERE
            (
                (
                    ra.type_quantite LIKE 'reference' AND
                    (
                        (ra.limit_security IS NOT NULL AND ra.limit_security > 0 AND ra.quantite_stock <= ra.limit_security)
                        OR
                        (ra.limit_warning IS NOT NULL AND ra.limit_warning > 0 AND ra.quantite_stock <= ra.limit_warning)
                    )
                )
                OR
                (
                    ra.type_quantite LIKE 'article' AND
                    (
                        (
                            (
                                SELECT IF(SUM(art1.quantite) IS NULL, 0, SUM(art1.quantite))
                                FROM article art1
                                INNER JOIN article_fournisseur af1 ON af1.id = art1.article_fournisseur_id
                                INNER JOIN (SELECT * FROM reference_article) refart1 ON refart1.id = af1.reference_article_id
                                INNER JOIN statut s1 ON s1.id = art1.statut_id
                                WHERE s1.nom LIKE 'disponible' AND refart1.id = ra.id
                            ) <= ra.limit_warning
                            AND ra.limit_warning IS NOT NULL
                            AND ra.limit_warning > 0
                        )
                        OR
                        (
                            (
                                SELECT IF(SUM(art2.quantite) IS NULL, 0, SUM(art2.quantite))
                                FROM article art2
                                INNER JOIN article_fournisseur af2 ON af2.id = art2.article_fournisseur_id
                                INNER JOIN (SELECT * FROM reference_article) refart2 ON refart2.id = af2.reference_article_id
                                INNER JOIN statut s2 ON s2.id = art2.statut_id
                                WHERE s2.nom LIKE 'disponible' AND refart2.id = ra.id
                            ) <= ra.limit_security
                            AND ra.limit_security IS NOT NULL
                            AND ra.limit_security > 0
                        )
                    )
                )
            )
            AND ra.date_emergency_triggered IS NULL
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reference_article DROP date_emergency_triggered');

    }
}
