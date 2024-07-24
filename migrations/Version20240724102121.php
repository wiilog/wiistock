<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240724102121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE inventory_mission_rule RENAME TO inventory_mission_plan;');
        $this->addSql('ALTER TABLE inventory_mission_rule_emplacement RENAME TO inventory_mission_plan_emplacement;');
        $this->addSql('ALTER TABLE inventory_mission_rule_inventory_category RENAME TO inventory_mission_plan_inventory_category;');

        $this->addSql('ALTER TABLE inventory_mission_plan ADD schedule_rule_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE inventory_mission_plan_emplacement CHANGE inventory_mission_rule_id inventory_mission_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE inventory_mission_plan_inventory_category CHANGE inventory_mission_rule_id inventory_mission_plan_id INT NOT NULL');

        $missionPlan = $this->connection
            ->executeQuery("
                    SELECT *
                    FROM inventory_mission_rule
                ")
            ->iterateAssociative();
        foreach ($missionPlan as $plan) {
            $this->addSql("
                INSERT INTO schedule_rule
                    (begin, frequency, period, interval_time, interval_period, week_days, month_days, months, last_run)
                VALUES
                    (:begin, :frequency, :period, :interval_time, :interval_period, :week_days, :month_days, :months, :last_run)
            ", [
                "begin" => $plan["begin"],
                "frequency" => $plan["frequency"],
                "period" => $plan["period"],
                "interval_time" => $plan["interval_time"],
                "interval_period" => $plan["interval_period"],
                "week_days" => $plan["week_days"],
                "month_days" => $plan["month_days"],
                "months" => $plan["months"],
                "last_run" => $plan["last_run"],
            ]);
            $this->addSql("
                UPDATE inventory_mission_plan
                SET schedule_rule_id = LAST_INSERT_ID()
                WHERE inventory_mission_plan.id = :id
            ", [
                "id" => $plan["id"],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
