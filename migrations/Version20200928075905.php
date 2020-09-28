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

        $dropzoneCode = LocationCluster::CLUSTER_CODE_DOCK_DASHBOARD_DROPZONE;
        $this->migrateParameters('DASHBOARD_LOCATION_TO_DROP_ZONES', $dropzoneCode);
        $locationClusterInto = "
            SELECT id
            FROM location_cluster
            WHERE code = '{$dropzoneCode}'
        ";

        $nbDaysToReturn = 15;
        $locationRaw = $this->connection
            ->executeQuery("SELECT value FROM parametrage_global WHERE parametrage_global.label = 'DASHBOARD_LOCATION_TO_DROP_ZONES'")
            ->fetchColumn();


        $dropType = MouvementTraca::TYPE_DEPOSE;

        if (!empty($locationRaw)) {
            for($dayIndex = 0; $dayIndex < $nbDaysToReturn; $dayIndex++) {
                $dateToCheck = new DateTime("now - $dayIndex days", new DateTimeZone('Europe/Paris'));
                $dateToCheckBegin = (clone $dateToCheck)->setTime(0, 0);
                $dateToCheckEnd = (clone $dateToCheck)->setTime(23, 59, 59);

                $dateToCheckStr = $dateToCheck->format('Y-m-d');
                $dateToCheckStrBegin = $dateToCheckBegin->format('Y-m-d H:i:s');
                $dateToCheckStrEnd = $dateToCheckEnd->format('Y-m-d  H:i:s');

                $counter = $this->connection
                    ->executeQuery("
                        SELECT COUNT(mouvement_traca.id) AS counter
                        FROM mouvement_traca
                        INNER JOIN statut on mouvement_traca.type_id = statut.id
                        WHERE datetime BETWEEN '{$dateToCheckStrBegin}' AND {$dateToCheckStrEnd}
                          AND statut.nom = '{$dropType}'
                          AND mouvement_traca.emplacement_id IN ({$locationRaw})
                      ")
                    ->fetchColumn();

                $this->addSql("
                    INSERT INTO location_cluster_meter (location_cluster_from_id, location_cluster_into_id, `date`, drop_counter)
                    VALUES (NULL, {$locationClusterInto}, '{$dateToCheckStr}', {$counter})
                ");
            }
        }
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
}
