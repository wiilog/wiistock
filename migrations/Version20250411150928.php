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
final class Version20250411150928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $subMenus = [
            ActionsFixtures::SUB_MENU_EMERGENCIES => [
                Action::DISPLAY_URGE,
                Action::CREATE_EMERGENCY,
            ],
            ActionsFixtures::SUB_MENU_DISPUTE => [
                Action::DISPLAY_LITI,
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::TREAT_DISPUTE,
            ],
        ];

        foreach ($subMenus as $subMenu => $actions) {
            $this->addSql(
                'INSERT INTO sub_menu (menu_id, label)
                VALUE ((SELECT id FROM menu WHERE label = :menu), :subMenu)',
                [
                    'subMenu' => $subMenu,
                    'menu' => Menu::QUALI,
                ]
            );

            foreach ($actions as $action) {
                $this->addSql(
                    'UPDATE action
                    INNER JOIN menu action_menu ON action.menu_id = action_menu.id
                    SET action.sub_menu_id = (
                        SELECT sub_menu.id
                        FROM sub_menu
                        INNER JOIN menu ON sub_menu.menu_id = menu.id
                        WHERE sub_menu.label = :subMenu AND menu.label = :newMenu
                        LIMIT 1
                    ), action.menu_id = (SELECT menu.id FROM menu WHERE menu.label = :newMenu LIMIT 1)
                    WHERE action_menu.label = :oldMenu
                      AND action.label = :action',
                    [
                        'newMenu' => Menu::QUALI,
                        'oldMenu' => $subMenu === ActionsFixtures::SUB_MENU_EMERGENCIES
                            ? Menu::TRACA
                            : Menu::QUALI,
                        'subMenu' => $subMenu,
                        'action' => $action
                    ]
                );
            }
        }

        $this->addSql(
            'DELETE FROM sub_menu
                 WHERE sub_menu.menu_id = (SELECT menu.id FROM menu WHERE menu.label = :menu LIMIT 1)
                 AND sub_menu.label = :subMenu',
            [
                'subMenu' => ActionsFixtures::SUB_MENU_EMERGENCYS,
                'menu' => Menu::TRACA,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
