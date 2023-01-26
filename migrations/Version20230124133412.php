<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Inventory\InventoryMission;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230124133412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $inventoryTypeArticle = InventoryMission::ARTICLE_TYPE;
        if(!$schema->getTable("inventory_mission")->hasColumn("type")) {
            $this->addSql("ALTER TABLE inventory_mission ADD COLUMN type VARCHAR(255)");
        }
        $this->addSql("UPDATE inventory_mission SET type = '$inventoryTypeArticle'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
