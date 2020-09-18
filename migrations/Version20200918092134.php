<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200918092134 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE `dispatch` ADD `emergency` VARCHAR(255) DEFAULT NULL");
        $this->addSql("UPDATE `dispatch` SET `emergency` = 'Urgent' WHERE `urgent` = 1");
        $this->addSql("ALTER TABLE `dispatch` DROP `urgent`");
    }

    public function down(Schema $schema): void {
        $this->addSql("ALTER TABLE `dispatch` ADD `urgent` VARCHAR(255) DEFAULT NULL");
        $this->addSql("UPDATE `dispatch` SET `urgent` = (`emergency` IS NOT NULL AND `emergency` != '')");
        $this->addSql("ALTER TABLE `dispatch` DROP `emergency`");
    }

}
