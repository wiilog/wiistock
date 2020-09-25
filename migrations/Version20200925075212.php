<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\LocationCluster;
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

        $this->migrate('DASHBOARD_LOCATIONS_1', LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1);
        $this->migrate('DASHBOARD_LOCATIONS_2', LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2);
    }

    public function down(Schema $schema) : void
    {
    }

    /**
     * @param string $oldLabel
     * @param string $clusterCode
     * @throws DBALException
     */
    public function migrate(string $oldLabel, string $clusterCode) {
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

        $this->addSql("DELETE FROM parametrage_global WHERE parametrage_global.label = '{$oldLabel}'");
    }
}
