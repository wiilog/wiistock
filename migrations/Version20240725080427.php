<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240725080427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        /// EXPORTS ///
        $exports = $this->connection
            ->executeQuery("
                SELECT export.id AS export_id,
                       schedule_rule.last_run AS last_run
                FROM export
                    INNER JOIN schedule_rule ON export.schedule_rule_id = schedule_rule.id
                WHERE schedule_rule.last_run IS NOT NULL")
            ->iterateAssociative();

        $this->addSql("ALTER TABLE export ADD last_run DATETIME DEFAULT NULL;");
        foreach ($exports as $export) {
            $this->addSql("
                UPDATE export
                SET export.last_run = :last_run
                WHERE export.id = :id
            ", [
                "id" => $export["export_id"],
                "last_run" => $export["last_run"],
            ]);
        }

        /// IMPORTS ///
        $imports = $this->connection
            ->executeQuery("
                SELECT import.id AS import_id,
                       schedule_rule.last_run AS last_run
                FROM import
                    INNER JOIN schedule_rule ON import.schedule_rule_id = schedule_rule.id
                WHERE schedule_rule.last_run IS NOT NULL")
            ->iterateAssociative();

        $this->addSql("ALTER TABLE import ADD last_run DATETIME DEFAULT NULL;");
        foreach ($imports as $import) {
            $this->addSql("
                UPDATE import
                SET import.last_run = :last_run
                WHERE import.id = :id
            ", [
                "id" => $import["import_id"],
                "last_run" => $import["last_run"],
            ]);
        }

        /// INVENTORY MISSION ///
        $inventoryMissionPlans = $this->connection
            ->executeQuery("
                SELECT inventory_mission_plan.id AS inventory_mission_plan_id,
                       schedule_rule.last_run AS last_run
                FROM inventory_mission_plan
                    INNER JOIN schedule_rule ON inventory_mission_plan.schedule_rule_id = schedule_rule.id
                WHERE schedule_rule.last_run IS NOT NULL")
            ->iterateAssociative();

        $this->addSql("ALTER TABLE inventory_mission_plan ADD last_run DATETIME DEFAULT NULL;");
        foreach ($inventoryMissionPlans as $inventoryMissionPlan) {
            $this->addSql("
                UPDATE inventory_mission_plan
                SET inventory_mission_plan.last_run = :last_run
                WHERE inventory_mission_plan.id = :id
            ", [
                "id" => $inventoryMissionPlan["inventory_mission_plan_id"],
                "last_run" => $inventoryMissionPlan["last_run"],
            ]);
        }

        /// PURCHASE REQUEST ///
        $purchaseRequestPlans = $this->connection
            ->executeQuery("
                SELECT purchase_request_plan.id AS purchase_request_plan_id,
                       schedule_rule.last_run AS last_run
                FROM purchase_request_plan
                    INNER JOIN schedule_rule ON purchase_request_plan.schedule_rule_id = schedule_rule.id
                WHERE schedule_rule.last_run IS NOT NULL")
            ->iterateAssociative();

        $this->addSql("ALTER TABLE purchase_request_plan ADD last_run DATETIME DEFAULT NULL;");
        foreach ($purchaseRequestPlans as $purchaseRequestPlan) {
            $this->addSql("
                UPDATE purchase_request_plan
                SET purchase_request_plan.last_run = :last_run
                WHERE purchase_request_plan.id = :id
            ", [
                "id" => $purchaseRequestPlan["purchase_request_plan_id"],
                "last_run" => $purchaseRequestPlan["last_run"],
            ]);
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
