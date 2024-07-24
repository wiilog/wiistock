<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240715132831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE schedule_rule (
                id INT AUTO_INCREMENT NOT NULL,
                begin DATETIME NOT NULL,
                frequency VARCHAR(255) DEFAULT NULL,
                `period` INT DEFAULT NULL,
                interval_time VARCHAR(255) DEFAULT NULL,
                interval_period INT DEFAULT NULL,
                week_days JSON DEFAULT NULL,
                month_days JSON DEFAULT NULL,
                months JSON DEFAULT NULL,
                last_run DATETIME DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
        $this->addSql('ALTER TABLE export ADD schedule_rule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE import ADD schedule_rule_id INT DEFAULT NULL, ADD file_path VARCHAR(255) NOT NULL');

        $exportScheduleRules = $this->connection
            ->executeQuery("
                    SELECT export.id AS export_id,
                           export_schedule_rule.*
                    FROM export_schedule_rule
                    INNER JOIN export ON export.id = export_schedule_rule.export_id
                ")
            ->iterateAssociative();
        foreach ($exportScheduleRules as $rule) {
            $this->addSql("
                INSERT INTO schedule_rule
                    (begin, frequency, period, interval_time, interval_period, week_days, month_days, months, last_run)
                VALUES
                    (:begin, :frequency, :period, :interval_time, :interval_period, :week_days, :month_days, :months, :last_run)
            ", [
                "begin" => $rule["begin"],
                "frequency" => $rule["frequency"],
                "period" => $rule["period"],
                "interval_time" => $rule["interval_time"],
                "interval_period" => $rule["interval_period"],
                "week_days" => $rule["week_days"],
                "month_days" => $rule["month_days"],
                "months" => $rule["months"],
                "last_run" => $rule["last_run"],
            ]);
            $this->addSql("
                UPDATE export
                SET schedule_rule_id = LAST_INSERT_ID()
                WHERE export.id = :id
            ", [
                "id" => $rule["export_id"],
            ]);
        }

        $importScheduleRules = $this->connection
            ->executeQuery("
                    SELECT import.id AS import_id,
                           import_schedule_rule.*
                    FROM import_schedule_rule
                    INNER JOIN import ON import.id = import_schedule_rule.import_id
                ")
            ->iterateAssociative();
        foreach ($importScheduleRules as $rule) {
            $this->addSql("
                INSERT INTO schedule_rule
                    (begin, frequency, period, interval_time, interval_period, week_days, month_days, months, last_run)
                VALUES
                    (:begin, :frequency, :period, :interval_time, :interval_period, :week_days, :month_days, :months, :last_run)
            ", [
                "begin" => $rule["begin"],
                "frequency" => $rule["frequency"],
                "period" => $rule["period"],
                "interval_time" => $rule["interval_time"],
                "interval_period" => $rule["interval_period"],
                "week_days" => $rule["week_days"],
                "month_days" => $rule["month_days"],
                "months" => $rule["months"],
                "last_run" => $rule["last_run"],
            ]);
            $this->addSql("
                UPDATE import
                SET schedule_rule_id = LAST_INSERT_ID(),
                    file_path = :file_path
                WHERE import.id = :id
            ", [
                "id" => $rule["import_id"],
                "file_path" => $rule["file_path"],
            ]);
        }

        $this->addSql('DROP TABLE import_schedule_rule');
        $this->addSql('DROP TABLE export_schedule_rule');
    }

    public function down(Schema $schema): void
    {
    }
}
