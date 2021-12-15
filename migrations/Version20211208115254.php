<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\ActionsFixtures;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211208115254 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("CREATE TABLE sub_menu (id INT AUTO_INCREMENT NOT NULL, menu_id INT DEFAULT NULL, label VARCHAR(255) NOT NULL, INDEX IDX_5A93A552CCD7E912 (menu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE action ADD sub_menu_id INT DEFAULT NULL");

        foreach(ActionsFixtures::MENUS as $menu => $submenus) {
            $menuId = $this->connection->executeQuery("SELECT id FROM menu WHERE label = '$menu'")->fetchOne();

            foreach($submenus as $submenu => $actions) {
                if(is_string($submenu) && is_array($actions)) {
                    $this->addSql("INSERT INTO sub_menu (menu_id, label) VALUES ($menuId, '$submenu')");
                    $submenuFinder = "(SELECT id FROM sub_menu WHERE menu_id = $menuId AND label = '$submenu')";

                    foreach($actions as $action) {
                        $this->addSql("UPDATE action SET sub_menu_id = $submenuFinder WHERE menu_id = :menu AND label = :action", [
                            "menu" => $menuId,
                            "action" => $action,
                        ]);
                    }
                }
            }
        }
    }

}
