<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\ActionsFixtures;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\SubMenu;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231102152710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $actions = [
            Action::MODULE_ACCESS_TRACA => [
                Action::MODULE_ACCESS_MOVEMENTS,
                Action::MODULE_ACCESS_DISPATCHS
            ],
            Action::MODULE_ACCESS_STOCK => [
                Action::MODULE_ACCESS_PREPARATIONS,
                Action::MODULE_ACCESS_DELIVERY_ORDER,
                Action::MODULE_ACCESS_MANUAL_DELIVERY,
                Action::MODULE_ACCESS_COLLECT_ORDER,
                Action::MODULE_ACCESS_TRANSFER_ORDER,
                Action::MODULE_ACCESS_MANUAL_TRANSFER,
                Action::MODULE_ACCESS_INVENTORY,
                Action::MODULE_ACCESS_ARTICLES_UL_ASSOCIATION,
            ],
            Action::MODULE_ACCESS_HAND => [
                Action::MODULE_ACCESS_HANDLING,
                Action::MODULE_ACCESS_DELIVERY_REQUESTS,
            ],
        ];

        $subMenus = [
            Action::MODULE_ACCESS_TRACA => ActionsFixtures::SUB_MENU_TRACING,
            Action::MODULE_ACCESS_STOCK => ActionsFixtures::SUB_MENU_STOCK,
            Action::MODULE_ACCESS_HAND => ActionsFixtures::SUB_MENU_REQUESTS,
        ];

        foreach ($actions as $oldAction => $newActions){
            $oldActionId = $this->connection->executeQuery("SELECT id FROM action WHERE label = :oldAction", [
                "oldAction" => $oldAction
            ])->fetchOne() ?? null;

            $roles = $this->connection->executeQuery("SELECT role_id FROM action_role WHERE action_id = $oldActionId")->fetchAllAssociative();

            $menu = $this->connection->fetchOne("SELECT id FROM menu WHERE label = :label", ["label" => Menu::NOMADE]);
            $subMenu = $this->connection->fetchAllAssociative("SELECT id, menu_id FROM sub_menu WHERE label = '{$subMenus[$oldAction]}' AND menu_id = $menu")[0];

            foreach ($newActions as $newAction){
                $this->addSql("INSERT INTO action (label, sub_menu_id, menu_id) VALUES ('$newAction', {$subMenu['id']}, {$subMenu['menu_id']})");

                foreach ($roles as $role) {
                    $this->addSql("INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action ORDER BY id DESC LIMIT 1), {$role['role_id']})");
                }
            }
        }
    }

    public function down(Schema $schema): void
    {

    }
}
