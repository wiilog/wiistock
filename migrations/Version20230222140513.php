<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\ScheduleRule;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230222140513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate inventory_mission_rule to the new format';
    }

    public function up(Schema $schema): void
    {
        // mission_type
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN mission_type VARCHAR(255)');
        $this->addSql('UPDATE inventory_mission_rule SET mission_type = :article', ['article' => InventoryMission::ARTICLE_TYPE]);


        // begin
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN begin DATE');
        $this->addSql('UPDATE inventory_mission_rule SET begin = last_run');

        // period
        // create period column and set it to the value of periodocity
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN period INT');
        $this->addSql('UPDATE inventory_mission_rule SET period = periodicity');


        // interval time
        // new column 'interval_time' varchar(255) format: '15:00' 'hh:mm'
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN interval_time VARCHAR(255)');
        // set interval_time to the value of last_run hour and minute
        $this->addSql('UPDATE inventory_mission_rule SET interval_time = DATE_FORMAT(last_run, "%H:%i")');


        // periodicity / frequency
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN frequency VARCHAR(255)');
        // weekly
        $this->addSql('UPDATE inventory_mission_rule SET frequency = :weekly WHERE duration_unit = "weeks"', ['weekly' => ScheduleRule::WEEKLY]);

        // monthly
        $this->addSql('UPDATE inventory_mission_rule SET frequency = :monthly WHERE duration_unit = "months"', ['monthly' => ScheduleRule::MONTHLY]);


        // week_days
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN week_days JSON');
        // set week_days to the day of the week of last_run, type array (json) 1 = monday, 7 = sunday
        $this->addSql('UPDATE inventory_mission_rule SET week_days = JSON_ARRAY(DATE_FORMAT(last_run, "%w") +1 )');

        // month_days
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN month_days JSON');
        // set month_days to the day of the month of last_run, type array (json)
        $this->addSql('UPDATE inventory_mission_rule SET month_days = JSON_ARRAY(DATE_FORMAT(last_run, "%d"))');

        // months
        $this->addSql('ALTER TABLE inventory_mission_rule ADD COLUMN months JSON');
        // set months to all the months 1 = january, 12 = december
        $this->addSql('UPDATE inventory_mission_rule SET months = JSON_ARRAY(1,2,3,4,5,6,7,8,9,10,11,12)');
    }
}
