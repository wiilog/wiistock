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
final class Version20200730101853 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $ordreCollecteTreated = OrdreCollecte::STATUT_TRAITE;
        $demandeCollecteToTreat = Collecte::STATUT_A_TRAITER;
        $demandeCollecteTreated = Collecte::STATUT_COLLECTE;

        $demandeCollecteCategoryStatus = Collecte::CATEGORIE;

        $demandeIdsAndStatusOrders = $this
            ->connection
            ->executeQuery("
                SELECT ordre_collecte.demande_collecte_id
                    FROM ordre_collecte
                    INNER JOIN statut orderStatus on ordre_collecte.statut_id = orderStatus.id
                    INNER JOIN collecte on collecte.id = ordre_collecte.demande_collecte_id
                    INNER JOIN statut demandeStatus on collecte.statut_id = demandeStatus.id
                    WHERE orderStatus.nom = '${ordreCollecteTreated}'
                      AND demandeStatus.nom = '${demandeCollecteToTreat}'
            ")
            ->fetchAll();

        foreach ($demandeIdsAndStatusOrders as $row) {
            $demandeId = $row['demande_collecte_id'];
            $this->addSql("
                UPDATE collecte SET collecte.statut_id = (
                    SELECT statut.id
                    FROM statut
                        INNER JOIN categorie_statut on statut.categorie_id = categorie_statut.id
                    WHERE categorie_statut.nom = '${demandeCollecteCategoryStatus}'
                      AND statut.nom = '${demandeCollecteTreated}'
                )
                WHERE collecte.id = ${demandeId}");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
