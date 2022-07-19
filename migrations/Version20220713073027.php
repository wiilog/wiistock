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
final class Version20220713073027 extends AbstractMigration
{

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $newActionsWithSubMenus = [
            ActionsFixtures::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            ActionsFixtures::SUB_MENU_ARRIVALS => [
                Action::DISPLAY_ARRI,
                Action::LIST_ALL,
                Action::CREATE_ARRIVAL,
                Action::EDIT_ARRI,
                Action::DELETE_ARRI,
                Action::ADD_PACK,
                Action::EDIT_PACK,
                Action::DELETE_PACK,
            ],
            ActionsFixtures::SUB_MENU_MOVEMENTS => [
                Action::DISPLAY_MOUV,
                Action::CREATE_TRACKING_MOVEMENT,
                Action::FULLY_EDIT_TRACKING_MOVEMENTS,
                Action::EMPTY_ROUND,
            ],
            ActionsFixtures::SUB_MENU_PACKS => [
                Action::DISPLAY_PACK,
            ],
            ActionsFixtures::SUB_MENU_ASSOCIATION_BR => [
                Action::DISPLAY_ASSO,
            ],
            ActionsFixtures::SUB_MENU_ENCO => [
                Action::DISPLAY_ENCO,
            ],
            ActionsFixtures::SUB_MENU_EMERGENCYS => [
                Action::DISPLAY_URGE,
                Action::CREATE_EMERGENCY,
            ]
        ];

        $roles = $this->connection->executeQuery("SELECT id FROM role")->fetchAllAssociative();

        foreach ($newActionsWithSubMenus as $subMenu => $newActions) {
            $this->addSql("INSERT INTO sub_menu (menu_id, label) VALUE (
                    (SELECT id FROM menu WHERE menu.label = :menu),
                    :sub_menu
                )", [
                'menu' => Menu::TRACA,
                'sub_menu' => $subMenu
            ]);

            foreach ($newActions as $action){
                $existingId = $this->connection
                    ->executeQuery("
                    SELECT action.id AS action_id
                    FROM action
                        INNER JOIN menu on action.menu_id = menu.id
                    WHERE action.label = :action
                      AND menu.label = :menu
                ", [
                        "action" => $action,
                        'menu' => Menu::TRACA
                    ])
                    ->fetchOne();

                if(!$existingId){
                    $this->addSql("INSERT INTO action (menu_id, label, sub_menu_id) VALUE (
                        (SELECT id FROM menu WHERE menu.label = :menu),
                        :action,
                        (SELECT sub_menu.id FROM sub_menu INNER JOIN menu ON sub_menu.menu_id = menu.id WHERE menu.label = :menu AND sub_menu.label = :sub_menu)
                    )", [
                        "menu" => Menu::TRACA,
                        "action" => $action,
                        "sub_menu" => $subMenu
                    ]);

                    foreach ($roles as $role) {
                        $roleId = $role['id'];

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
                            "menu" => Menu::TRACA,
                            "sub_menu" => $subMenu,
                            "action" => $action,
                            "role_id" => $roleId,
                        ]);
                    }
                } else {
                    $this->addSql("
                        UPDATE action
                        INNER JOIN menu on action.menu_id = menu.id
                        SET sub_menu_id = (
                              SELECT sub_menu.id
                              FROM sub_menu
                                 INNER JOIN menu ON sub_menu.menu_id = menu.id
                              WHERE menu.label = :menu
                                AND sub_menu.label = :sub_menu
                            )
                        WHERE action.label = :action
                                  AND menu.label = :menu

                    ", [
                        "menu" => Menu::TRACA,
                        "sub_menu" => $subMenu,
                        "action" => $action,
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
