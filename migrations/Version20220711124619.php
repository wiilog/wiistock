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
final class Version20220711124619 extends AbstractMigration {

    const SETTINGS_GLOBAL = 'afficher paramétrage global';
    const SETTINGS_STOCK = 'afficher stock';
    const SETTINGS_TRACING = 'afficher trace';
    const SETTINGS_TRACKING = 'afficher track';
    const SETTINGS_MOBILE = 'afficher terminal mobile';
    const SETTINGS_DASHBOARDS = 'afficher dashboards';
    const SETTINGS_IOT = 'afficher iot';
    const SETTINGS_NOTIFICATIONS = 'afficher modèles de notifications';
    const SETTINGS_USERS = 'afficher utilisateurs';
    const SETTINGS_DATA = 'afficher données';

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $previousActionsForSubmenus = [
            ActionsFixtures::SUB_MENU_GENERAL => null,
            ActionsFixtures::SUB_MENU_GLOBAL => self::SETTINGS_GLOBAL,
            ActionsFixtures::SUB_MENU_STOCK => self::SETTINGS_STOCK,
            ActionsFixtures::SUB_MENU_TRACING => self::SETTINGS_TRACING,
            ActionsFixtures::SUB_MENU_TRACKING => self::SETTINGS_TRACKING,
            ActionsFixtures::SUB_MENU_TERMINAL_MOBILE => self::SETTINGS_MOBILE,
            ActionsFixtures::SUB_MENU_DASHBOARD => self::SETTINGS_DASHBOARDS,
            ActionsFixtures::SUB_MENU_IOT => self::SETTINGS_IOT,
            ActionsFixtures::SUB_MENU_NOTIFICATIONS => self::SETTINGS_NOTIFICATIONS,
            ActionsFixtures::SUB_MENU_USERS => self::SETTINGS_USERS,
            ActionsFixtures::SUB_MENU_DATA => self::SETTINGS_DATA,
        ];

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
                Action::SETTINGS_DISPLAY_TOUCH_TERMINAL,
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
            $this->addSql("INSERT INTO sub_menu (menu_id, label) VALUE (
                    (SELECT id FROM menu WHERE menu.label = :menu),
                    :sub_menu
                )", [
                'menu' => Menu::PARAM,
                'sub_menu' => $subMenu
            ]);

            foreach ($newActions as $action){
                $this->addSql("INSERT INTO action (menu_id, label, sub_menu_id) VALUE (
                    (SELECT id FROM menu WHERE menu.label = :menu),
                    :action,
                    (SELECT sub_menu.id FROM sub_menu INNER JOIN menu ON sub_menu.menu_id = menu.id WHERE menu.label = :menu AND sub_menu.label = :sub_menu)
                )", [
                    "menu" => Menu::PARAM,
                    "action" => $action,
                    "sub_menu" => $subMenu
                ]);

                foreach ($roles as $role) {
                    $roleId = $role['id'];

                    $hasPermission = $this->connection->executeQuery(
                        "SELECT *
                        FROM action_role
                            INNER JOIN action ON action_role.action_id = action.id
                            INNER JOIN menu ON action.menu_id = menu.id
                        WHERE menu.label = :menu
                            AND action.label = :action
                            AND action_role.role_id = :role",
                        [
                        "menu" => Menu::PARAM,
                        // "?? $action" => pour les droits modifier et supprimer qui ne deviennent pas des submenus
                        "action" => $previousActionsForSubmenus[$subMenu] ?? $action,
                        "role" => $roleId,
                        ]
                    )->rowCount() > 0;

                    if($hasPermission) {
                        $this->addSql("
                            INSERT INTO action_role (action_id, role_id) VALUE (
                                (
                                    SELECT action.id
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
                            "menu" => Menu::PARAM,
                            "sub_menu" => $subMenu,
                            "action" => $action,
                            "role_id" => $roleId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
