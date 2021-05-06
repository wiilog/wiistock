<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210505133530 extends AbstractMigration
{

    const LABELS_TO_DELETE = [
        'DASHBOARD NATURE COLIS',
        'DASHBOARD LIST NATURES COLIS',
        'DASHBOARD_LOCATION_DOCK',
        'DASHBOARD_LOCATION_WAITING_CLEARANCE',
        'DASHBOARD_LOCATION_AVAILABLE',
        'DASHBOARD_LOCATION_TO_DROP_ZONES',
        'DASHBOARD_LOCATION_LITIGE',
        'DASHBOARD_LOCATION_URGENCES',
        'DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK',
        'DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN',
        'DASHBOARD_CARRIER_DOCK',
        'DASHBOARD_PACKAGING_1',
        'DASHBOARD_PACKAGING_2',
        'DASHBOARD_PACKAGING_3',
        'DASHBOARD_PACKAGING_4',
        'DASHBOARD_PACKAGING_5',
        'DASHBOARD_PACKAGING_6',
        'DASHBOARD_PACKAGING_7',
        'DASHBOARD_PACKAGING_8',
        'DASHBOARD_PACKAGING_9',
        'DASHBOARD_PACKAGING_10',
        'DASHBOARD_PACKAGING_RPA',
        'DASHBOARD_PACKAGING_LITIGE',
        'DASHBOARD_PACKAGING_URGENCE',
        'DASHBOARD_PACKAGING_ORIGINE_GT',
        'DASHBOARD_PACKAGING_KITTING',
    ];

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('DROP TABLE IF EXISTS dashboard_chart_meter');
        $this->addSql('DROP TABLE IF EXISTS dashboard_meter');
        $this->addSql("DELETE FROM `parametrage_global` WHERE label IN ('" . implode("','", self::LABELS_TO_DELETE) . "')");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
