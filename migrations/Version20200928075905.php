<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\LocationCluster;
use App\Entity\MouvementTraca;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200928075905 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE location_cluster_meter (id INT AUTO_INCREMENT NOT NULL, location_cluster_from_id INT DEFAULT NULL, location_cluster_into_id INT NOT NULL, date DATE NOT NULL, drop_counter INT UNSIGNED NOT NULL, PRIMARY KEY(id))');

        $this->migrateParameters('DASHBOARD_LOCATION_TO_DROP_ZONES', LocationCluster::CLUSTER_CODE_DOCK_DASHBOARD_DROPZONE);
        $this->migrateParameters('DASHBOARD_PACKAGING_DSQR', LocationCluster::CLUSTER_CODE_PACKAGING_DSQR);
        $this->migrateParameters('DASHBOARD_PACKAGING_DESTINATION_GT', LocationCluster::CLUSTER_CODE_PACKAGING_GT_TARGET);
        $this->migrateParameters('DASHBOARD_PACKAGING_ORIGIN_GT', LocationCluster::CLUSTER_CODE_PACKAGING_GT_ORIGIN);

        $this->migrateDropzoneChart();
        $this->migratePackagingChart();

        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_DSQR'");
        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_DESTINATION_GT'");
        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_ORIGIN_GT'");
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

    private function migrateDropzoneChart() {
        $dropzoneCode = LocationCluster::CLUSTER_CODE_DOCK_DASHBOARD_DROPZONE;
        $locationClusterIntoQuery = "
            SELECT id
            FROM location_cluster
            WHERE code = '{$dropzoneCode}'
        ";
        $locationRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_LOCATION_TO_DROP_ZONES'")
            ->fetchColumn();

        $dropzoneDataRaw = $this->connection
            ->executeQuery("
                SELECT data
                FROM dashboard_chart_meter
                WHERE chart_key = 'colis'")
            ->fetchColumn();

        if (!empty($locationRaw) && !empty($dropzoneDataRaw)) {
            $dropzoneData = json_decode($dropzoneDataRaw, true);
            foreach ($dropzoneData as $datum) {
                $keys = array_keys($datum['data']);
                $key = $keys[0];
                $date = DateTime::createFromFormat('d/m/Y', "$key/2020");
                $englishFormat = $date->format('Y-m-d');
                $counter = $datum['data'][$key];

                $this->addSql("
                    INSERT INTO location_cluster_meter (location_cluster_from_id, location_cluster_into_id, `date`, drop_counter)
                    VALUES (NULL, ({$locationClusterIntoQuery}), '{$englishFormat}', {$counter})
                ");
            }
        }
    }

    private function migratePackagingChart() {
        $dsqrCode = LocationCluster::CLUSTER_CODE_PACKAGING_DSQR;
        $originCode = LocationCluster::CLUSTER_CODE_PACKAGING_GT_ORIGIN;
        $targetCode = LocationCluster::CLUSTER_CODE_PACKAGING_GT_TARGET;
        $locationClusterDSQRIntoQuery = "SELECT id FROM location_cluster WHERE code = '{$dsqrCode}'";
        $locationClusterGTIntoQuery = "SELECT id FROM location_cluster WHERE code = '{$targetCode}'";
        $locationClusterGTFromQuery = "SELECT id FROM location_cluster WHERE code = '{$originCode}'";

        $locationDSQRRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_DSQR'")
            ->fetchColumn();

        $locationOriginRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_ORIGINE_GT'")
            ->fetchColumn();

        $locationTargetRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_PACKAGING_DESTINATION_GT'")
            ->fetchColumn();

        $packagingDataRaw = $this->connection
            ->executeQuery("
                SELECT data
                FROM dashboard_chart_meter
                WHERE chart_key = 'of'")
            ->fetchColumn();

        if (!empty($packagingDataRaw)
            && (
                !empty($locationDSQRRaw)
                || (!empty($locationOriginRaw) && !empty($locationTargetRaw))
            )) {
            $labelGT = 'OF traités par GT';
            $labelDSQR = 'OF envoyés par le DSQR';

            $packagingData = json_decode($packagingDataRaw, true);
            foreach ($packagingData as $datum) {
                $counterGT = $datum['data'][$labelGT] ?? null;
                $counterDSQR = $datum['data'][$labelDSQR] ?? null;

                $dayKey = $datum['dataKey'];
                $date = DateTime::createFromFormat('d/m/Y', "$dayKey/2020");
                $englishFormat = $date->format('Y-m-d');

                if (!empty($counterDSQR)) {
                    $this->addSql("
                        INSERT INTO location_cluster_meter (location_cluster_from_id, location_cluster_into_id, `date`, drop_counter)
                        VALUES (NULL, ({$locationClusterDSQRIntoQuery}), '{$englishFormat}', {$counterDSQR})
                    ");
                }

                if (!empty($counterGT)) {
                    $this->addSql("
                        INSERT INTO location_cluster_meter (location_cluster_from_id, location_cluster_into_id, `date`, drop_counter)
                        VALUES (({$locationClusterGTFromQuery}), ({$locationClusterGTIntoQuery}), '{$englishFormat}', {$counterGT})
                    ");
                }
            }
        }
    }
}
