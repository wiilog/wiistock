<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\ActionsFixtures;
use App\Entity\Action;
use App\Entity\Menu;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220711124619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $newActionsWithSubMenus = [
            ActionsFixtures::SUB_MENU_GENERAL => [

                Action::EDIT,
                Action::DELETE,
            ],
            ActionsFixtures::SUB_MENU_GLOBAL => [
                Action::SETTINGS_DISPLAY_WEBSITE_APPEARANCE,
                Action::SETTINGS_DISPLAY_APPLICATION_CLIENT,
                Action::SETTINGS_DISPLAY_BILL,
                Action::SETTINGS_DISPLAY_WORKING_HOURS,
                Action::SETTINGS_DISPLAY_NOT_WORKING_DAYS,
                Action::SETTINGS_DISPLAY_MAIL_SERVER,
            ],
            ActionsFixtures::SUB_MENU_STOCK => [
                Action::SETTINGS_DISPLAY_CONFIGURATIONS,
                Action::SETTINGS_DISPLAY_STOCK_ALERTS,
                Action::SETTINGS_DISPLAY_ARTICLES,
                Action::SETTINGS_DISPLAY_TACTILE_TERMINAL,
                Action::SETTINGS_DISPLAY_REQUESTS,
                Action::SETTINGS_DISPLAY_VISIBILITY_GROUPS,
                Action::SETTINGS_DISPLAY_INVENTORIES,
                Action::SETTINGS_DISPLAY_RECEP
            ],
            ActionsFixtures::SUB_MENU_TRACING => [
                Action::SETTINGS_DISPLAY_TRACING_DISPATCH,
                Action::SETTINGS_DISPLAY_ARRI,
                Action::SETTINGS_DISPLAY_MOVEMENT,
                Action::SETTINGS_DISPLAY_TRACING_HAND
            ],
            ActionsFixtures::SUB_MENU_TRACKING => [
                Action::SETTINGS_DISPLAY_TRACK_REQUESTS,
                Action::SETTINGS_DISPLAY_ROUND,
                Action::SETTINGS_DISPLAY_TEMPERATURES
            ],
            ActionsFixtures::SUB_MENU_TERMINAL_MOBILE => [
                Action::SETTINGS_DISPLAY_MOBILE_DISPATCH,
                Action::SETTINGS_DISPLAY_MOBILE_HAND,
                Action::SETTINGS_DISPLAY_TRANSFER_TO_TREAT,
                Action::SETTINGS_DISPLAY_PREPA,
                Action::SETTINGS_DISPLAY_MANAGE_VALIDATIONS
            ],
            ActionsFixtures::SUB_MENU_DASHBOARD => [
                Action::SETTINGS_DISPLAY_DASHBOARD
            ],
            ActionsFixtures::SUB_MENU_IOT => [
                Action::SETTINGS_DISPLAY_IOT
            ],
            ActionsFixtures::SUB_MENU_NOTIFICATIONS => [
                Action::SETTINGS_DISPLAY_NOTIFICATIONS_ALERTS,
                Action::SETTINGS_DISPLAY_NOTIFICATIONS_PUSH
            ],
            ActionsFixtures::SUB_MENU_USERS => [
                Action::SETTINGS_DISPLAY_LABELS_PERSO,
                Action::SETTINGS_DISPLAY_ROLES,
                Action::SETTINGS_DISPLAY_USERS
            ],
            ActionsFixtures::SUB_MENU_DATA => [
                Action::SETTINGS_DISPLAY_EXPORT,
                Action::SETTINGS_DISPLAY_IMPORTS_MAJS,
                Action::SETTINGS_DISPLAY_INVENTORIES_IMPORT
            ],
        ];

        $roles = $this->connection->executeQuery("SELECT id FROM role")->fetchAllAssociative();

        foreach ($newActionsWithSubMenus as $subMenu => $newActions) {
            foreach ($newActions as $action){
                $this->addSql("INSERT INTO action (menu_id, label, sub_menu_id) VALUE (
                    (SELECT id FROM menu WHERE menu.label = :menu),
                    :action,
                    (SELECT id FROM sub_menu WHERE sub_menu.label = :sub_menu LIMIT 1)
                )", [
                    'menu' => Menu::PARAM,
                    'action' => $action,
                    'sub_menu' => $subMenu
                ]);

                foreach ($roles as $role) {
                    $roleId = $role['id'];
                    $this->addSql("
                        INSERT INTO action_role (action_id, role_id) VALUE (
                            (
                                SELECT action.id AS id
                                FROM action
                                    INNER JOIN menu on action.menu_id = menu.id
                                    INNER JOIN sub_menu on action.sub_menu_id = sub_menu.id
                                WHERE action.label = :action
                                  AND menu.label = :menu
                                  AND sub_menu.label = :sub_menu
                            ),
                            :role_id
                        )
                    ", [
                        "action" => $action,
                        'menu' => Menu::PARAM,
                        'sub_menu' => $subMenu,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
