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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE export DROP FOREIGN KEY FK_428C1694F9242FE0');
        $this->addSql('ALTER TABLE import DROP FOREIGN KEY FK_9D4ECE1DF9242FE0');
        $this->addSql('CREATE TABLE export_schedule_rule (id INT AUTO_INCREMENT NOT NULL, export_id INT NOT NULL, begin DATETIME NOT NULL, frequency VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, period INT DEFAULT NULL, interval_time VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, interval_period INT DEFAULT NULL, week_days JSON DEFAULT NULL, month_days JSON DEFAULT NULL, months JSON DEFAULT NULL, last_run DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_AA66B6464CDAF82 (export_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fixed_field_by_type_on_label (fixed_field_by_type_id INT NOT NULL, type_id INT NOT NULL, INDEX IDX_B74792E4796CAB7C (fixed_field_by_type_id), INDEX IDX_B74792E4C54C8C93 (type_id), PRIMARY KEY(fixed_field_by_type_id, type_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE import_schedule_rule (id INT AUTO_INCREMENT NOT NULL, import_id INT NOT NULL, begin DATETIME NOT NULL, frequency VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, period INT DEFAULT NULL, interval_time VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, interval_period INT DEFAULT NULL, week_days JSON DEFAULT NULL, month_days JSON DEFAULT NULL, months JSON DEFAULT NULL, last_run DATETIME DEFAULT NULL, file_path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_F458E03FB6A263D9 (import_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE printer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, address VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, width DOUBLE PRECISION NOT NULL, height DOUBLE PRECISION NOT NULL, dpi INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reception_arrivage (reception_id INT NOT NULL, arrivage_id INT NOT NULL, INDEX IDX_6CB78C7C7C14DF52 (reception_id), INDEX IDX_6CB78C7CEB6A74D2 (arrivage_id), PRIMARY KEY(reception_id, arrivage_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE status_accessible_by (status_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_A9C66F706BF700BD (status_id), INDEX IDX_A9C66F70D60322AC (role_id), PRIMARY KEY(status_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE status_excluded_nature (status_id INT NOT NULL, nature_id INT NOT NULL, INDEX IDX_31CC7A5B3BCB2E4B (nature_id), INDEX IDX_31CC7A5B6BF700BD (status_id), PRIMARY KEY(status_id, nature_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE status_visible_by (status_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_D94B4B896BF700BD (status_id), INDEX IDX_D94B4B89D60322AC (role_id), PRIMARY KEY(status_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE transport_history (id INT AUTO_INCREMENT NOT NULL, request_id INT DEFAULT NULL, order_id INT DEFAULT NULL, user_id INT DEFAULT NULL, round_id INT DEFAULT NULL, deliverer_id INT DEFAULT NULL, pack_id INT DEFAULT NULL, location_id INT DEFAULT NULL, status_history_id INT DEFAULT NULL, date DATETIME NOT NULL, status_date DATETIME DEFAULT NULL, type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, reason LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, comment LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_B36DB0BA1919B217 (pack_id), INDEX IDX_B36DB0BA1F5660D4 (status_history_id), INDEX IDX_B36DB0BA427EB8A5 (request_id), INDEX IDX_B36DB0BA64D218E (location_id), INDEX IDX_B36DB0BA8D9F6D38 (order_id), INDEX IDX_B36DB0BAA6005CA0 (round_id), INDEX IDX_B36DB0BAA76ED395 (user_id), INDEX IDX_B36DB0BAB6A6A3F4 (deliverer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE transport_history_attachment (transport_history_id INT NOT NULL, attachment_id INT NOT NULL, INDEX IDX_DC93334A464E68B (attachment_id), INDEX IDX_DC93334A794E0D18 (transport_history_id), PRIMARY KEY(transport_history_id, attachment_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_printer (user_id INT NOT NULL, printer_id INT NOT NULL, INDEX IDX_D54167E746EC494A (printer_id), INDEX IDX_D54167E7A76ED395 (user_id), PRIMARY KEY(user_id, printer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE export_schedule_rule ADD CONSTRAINT FK_AA66B6464CDAF82 FOREIGN KEY (export_id) REFERENCES export (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE fixed_field_by_type_on_label ADD CONSTRAINT FK_B74792E4796CAB7C FOREIGN KEY (fixed_field_by_type_id) REFERENCES fixed_field_by_type (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fixed_field_by_type_on_label ADD CONSTRAINT FK_B74792E4C54C8C93 FOREIGN KEY (type_id) REFERENCES type (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE import_schedule_rule ADD CONSTRAINT FK_F458E03FB6A263D9 FOREIGN KEY (import_id) REFERENCES import (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reception_arrivage ADD CONSTRAINT FK_6CB78C7C7C14DF52 FOREIGN KEY (reception_id) REFERENCES reception (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reception_arrivage ADD CONSTRAINT FK_6CB78C7CEB6A74D2 FOREIGN KEY (arrivage_id) REFERENCES arrivage (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE status_accessible_by ADD CONSTRAINT FK_A9C66F706BF700BD FOREIGN KEY (status_id) REFERENCES statut (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE status_accessible_by ADD CONSTRAINT FK_A9C66F70D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE status_excluded_nature ADD CONSTRAINT FK_31CC7A5B3BCB2E4B FOREIGN KEY (nature_id) REFERENCES nature (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE status_excluded_nature ADD CONSTRAINT FK_31CC7A5B6BF700BD FOREIGN KEY (status_id) REFERENCES statut (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE status_visible_by ADD CONSTRAINT FK_D94B4B896BF700BD FOREIGN KEY (status_id) REFERENCES statut (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE status_visible_by ADD CONSTRAINT FK_D94B4B89D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user_printer ADD CONSTRAINT FK_D54167E746EC494A FOREIGN KEY (printer_id) REFERENCES printer (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user_printer ADD CONSTRAINT FK_D54167E7A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP TABLE schedule_rule');
        $this->addSql('DROP INDEX UNIQ_428C1694F9242FE0 ON export');
        $this->addSql('ALTER TABLE export DROP schedule_rule_id');
        $this->addSql('DROP INDEX UNIQ_9D4ECE1DF9242FE0 ON import');
        $this->addSql('ALTER TABLE import DROP schedule_rule_id, DROP file_path');
    }
}
