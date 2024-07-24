<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240724080848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE purchase_request_schedule_rule RENAME TO purchase_request_plan;');
        $this->addSql('ALTER TABLE purchase_request_schedule_rule_fournisseur RENAME TO purchase_request_plan_fournisseur;');
        $this->addSql('ALTER TABLE purchase_request_schedule_rule_zone RENAME TO purchase_request_plan_zone;');
        $this->addSql('ALTER TABLE purchase_request_plan ADD schedule_rule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_request_plan_zone CHANGE purchase_request_schedule_rule_id purchase_request_plan_id INT NOT NULL');
        $this->addSql('ALTER TABLE purchase_request_plan_fournisseur CHANGE purchase_request_schedule_rule_id purchase_request_plan_id INT NOT NULL');



        $requestPlan = $this->connection
            ->executeQuery("
                    SELECT *
                    FROM purchase_request_schedule_rule
                ")
            ->iterateAssociative();
        foreach ($requestPlan as $plan) {
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
                UPDATE purchase_request_plan
                SET schedule_rule_id = LAST_INSERT_ID()
                WHERE purchase_request_plan.id = :id
            ", [
                "id" => $plan["id"],
            ]);
        }


    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
