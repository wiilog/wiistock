<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200928081632 extends AbstractMigration {

    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        if(!$schema->getTable('utilisateur')->hasColumn('columns_visible_for_tracking_movement')) {
            $this->addSql("ALTER TABLE `utilisateur` ADD `columns_visible_for_tracking_movement` JSON DEFAULT NULL");
        }

        $columns = json_encode(Utilisateur::COL_VISIBLE_TRACKING_MOVEMENT_DEFAULT);
        $this->addSql("UPDATE `utilisateur` SET `columns_visible_for_tracking_movement` = '$columns' WHERE columns_visible_for_tracking_movement IS NULL");
    }

}
