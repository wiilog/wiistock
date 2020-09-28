<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200925144215 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $this->addSql("ALTER TABLE `handling` CHANGE `emergency` `previous_emergency` TINYINT(1) NOT NULL");
        $this->addSql("ALTER TABLE `handling` ADD `emergency` VARCHAR(255) DEFAULT NULL");
        $this->addSql("UPDATE `handling` SET `emergency` = '24h' WHERE `previous_emergency` = 1");
        $this->addSql("ALTER TABLE `handling` DROP `previous_emergency`");
    }

}
