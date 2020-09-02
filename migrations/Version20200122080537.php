<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200122080537 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $litigeStatut = Arrivage::STATUS_LITIGE;
        $confirmeStatut = Arrivage::STATUS_CONFORME;
        $categorieStatutArrivage = CategorieStatut::ARRIVAGE;

        $this->addSql('ALTER TABLE arrivage ADD statut_id INT DEFAULT NULL');
        $this->addSql("
            UPDATE arrivage a
            SET a.statut_id =
                (
                    SELECT s.id
                    FROM statut s
                    INNER JOIN categorie_statut cs on s.categorie_id = cs.id
                    WHERE s.nom LIKE IF(
                            (SELECT COUNT(*)
                            FROM colis c
                            INNER JOIN litige_colis lc on c.id = lc.colis_id
                            INNER JOIN litige l on l.id = lc.litige_id
                            INNER JOIN statut s2 on l.status_id = s2.id
                            WHERE s2.treated = false and c.arrivage_id = a.id) > 0,
                        '$litigeStatut',
                        '$confirmeStatut')
                      AND cs.nom LIKE '$categorieStatutArrivage'
                )
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
