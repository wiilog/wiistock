<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Collecte;
use App\Entity\OrdreCollecte;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201007121012 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $treatedCollectOrder = OrdreCollecte::STATUT_TRAITE;

        $this
            ->addSql('ALTER TABLE ordre_collecte ADD treating_date DATETIME DEFAULT NULL;');
        $this
            ->addSql("
                UPDATE
                    ordre_collecte
                INNER JOIN statut s on ordre_collecte.statut_id = s.id
                SET treating_date = ordre_collecte.date
                WHERE s.nom = '$treatedCollectOrder'
            ");

        $this
            ->addSql("
                 UPDATE
                    ordre_collecte
                 INNER JOIN collecte c on ordre_collecte.demande_collecte_id = c.id
                 SET ordre_collecte.date = c.validation_date
                 WHERE c.validation_date IS NOT NULL

            ");

        $this
            ->addSql("
                 UPDATE
                    collecte
                SET validation_date = date
            ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
