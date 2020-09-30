<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\LocationCluster;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200925075212 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE location_cluster (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_B807A3077153098 (code), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE location_cluster_emplacement (location_cluster_id INT NOT NULL, emplacement_id INT NOT NULL, INDEX IDX_F2867CA77A4232F7 (location_cluster_id), INDEX IDX_F2867CA7C4598A51 (emplacement_id), PRIMARY KEY(location_cluster_id, emplacement_id))');
        $this->addSql('CREATE TABLE location_cluster_record (id INT AUTO_INCREMENT NOT NULL, pack_id INT NOT NULL, first_drop_id INT DEFAULT NULL, last_tracking_id INT DEFAULT NULL, location_cluster_id INT NOT NULL, active TINYINT(1) NOT NULL, PRIMARY KEY(id))');


        $this->migrateParameters('DASHBOARD_LOCATIONS_1', LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1);
        $this->migrateParameters('DASHBOARD_LOCATIONS_2', LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2);

        $this->migrateLocationClusterRecord('DASHBOARD_LOCATIONS_1', ParametrageGlobal::DASHBOARD_NATURE_COLIS, LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1);
        $this->migrateLocationClusterRecord('DASHBOARD_LOCATIONS_2', ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS, LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2);

        $this->addSql("DELETE FROM dashboard_chart_meter WHERE dashboard_chart_meter.chart_key = 'admin-1'");
        $this->addSql("DELETE FROM dashboard_chart_meter WHERE dashboard_chart_meter.chart_key = 'admin-2'");
        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_LOCATIONS_1'");
        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_LOCATIONS_2'");
    }

    public function down(Schema $schema) : void
    {
    }

    /**
     * @param string $oldLabel
     * @param string $clusterCode
     * @throws DBALException
     */
    public function migrateParameters(string $oldLabel, string $clusterCode) {
        $dashboardLocationRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = '{$oldLabel}'")
            ->fetchColumn();

        $dashboardLocationIds = $dashboardLocationRaw ? explode(',', $dashboardLocationRaw) : [];
        $this->addSql("INSERT INTO location_cluster (`code`) VALUES ('{$clusterCode}')");
        foreach ($dashboardLocationIds as $dashboardLocationId) {
            $this->addSql("
                INSERT INTO location_cluster_emplacement (location_cluster_id, emplacement_id)
                VALUES ((SELECT id FROM location_cluster WHERE `code` = '{$clusterCode}' LIMIT 1), {$dashboardLocationId})
            ");
        }
    }

    private function migrateLocationClusterRecord(string $oldLabel, string $naturesParam, string $clusterCode): void {

        $dashboardLocationRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = '{$oldLabel}'")
            ->fetchColumn();
        $dashboardLocationArray = !empty($dashboardLocationRaw) ? explode(',', $dashboardLocationRaw) : [];

        $dashboardNatureRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = '{$naturesParam}'")
            ->fetchColumn();

        if (!empty($dashboardLocationArray) && !empty($dashboardNatureRaw)) {
            $packsOnClusters = $this->connection
                ->executeQuery("
                    SELECT pack.id AS pack_id,
                           mouvement_traca.id AS last_drop_id
                    FROM pack
                    INNER JOIN mouvement_traca on pack.last_drop_id = mouvement_traca.id
                    WHERE pack.nature_id IN ({$dashboardNatureRaw})
                      AND mouvement_traca.emplacement_id IN ({$dashboardLocationRaw})
                ")
                ->fetchAll();

            $locationClusterQuery = "
                SELECT location_cluster.id
                FROM location_cluster
                WHERE location_cluster.code = '{$clusterCode}'
                LIMIT 1
            ";

            foreach ($packsOnClusters as $packOnCluster) {
                $packId = $packOnCluster['pack_id'];
                $lastDropId = $packOnCluster['last_drop_id'];

                $trackingArray = $this->connection
                    ->executeQuery("
                        SELECT mouvement_traca.id AS tracking_id,
                               mouvement_traca.emplacement_id AS location_id,
                               type.nom AS type
                        FROM mouvement_traca
                            INNER JOIN statut type on mouvement_traca.type_id = type.id
                        WHERE mouvement_traca.pack_id = {$packId}
                          AND mouvement_traca.id <> {$lastDropId}
                        ORDER BY mouvement_traca.datetime DESC
                    ")
                    ->fetchAll();

                $firstDrop = $lastDropId;
                foreach ($trackingArray as $tracking) {
                    $locationId = $tracking['location_id'];
                    $trackingId = $tracking['tracking_id'];
                    $type = $tracking['type'];
                    if (!in_array($locationId, $dashboardLocationArray)) {
                        break;
                    }
                    else if ($type === MouvementTraca::TYPE_DEPOSE) {
                        $firstDrop = $trackingId;
                    }
                }

                $this->addSql("
                    INSERT INTO location_cluster_record (pack_id, first_drop_id, last_tracking_id, location_cluster_id, `active`)
                    VALUES ({$packId}, {$firstDrop}, {$lastDropId}, ({$locationClusterQuery}), 1)
                ");
            }
        }

    }
}
