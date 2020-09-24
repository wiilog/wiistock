<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200922101428 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        if (!$schema->getTable('utilisateur')->hasColumn('columns_visible_for_dispatch')) {
            $this->addSql("ALTER TABLE `utilisateur` ADD `columns_visible_for_dispatch` JSON DEFAULT NULL");
        }
        $columns = json_encode(Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT);
        $this->addSql("UPDATE `utilisateur` SET `columns_visible_for_dispatch` = '$columns' WHERE columns_visible_for_dispatch IS NULL");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
