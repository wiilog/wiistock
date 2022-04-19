<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\DashboardComponentTypesFixtures;
use App\Entity\Dashboard\ComponentType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211129111307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $dailyDispatchesMeterKey = ComponentType::DAILY_DISPATCHES;
        $dailyDispatchesHint = DashboardComponentTypesFixtures::COMPONENT_TYPES["Nombre d'acheminements quotidiens"]["hint"];
        $this->addSql("UPDATE dashboard_component_type SET hint = '" . str_replace("'", "''", $dailyDispatchesHint) . "' WHERE meter_key = '" . $dailyDispatchesMeterKey . "'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
