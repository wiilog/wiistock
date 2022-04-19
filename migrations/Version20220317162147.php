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
final class Version20220317162147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

//        Menu::REFERENTIEL
        $subMenus = [
            ActionsFixtures::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            ActionsFixtures::SUB_MENU_PAGE => [
                Action::DISPLAY_FOUR,
                Action::DISPLAY_EMPL,
                Action::DISPLAY_CHAU,
                Action::DISPLAY_TRAN,
                Action::DISPLAY_VEHICLE,
            ],
        ];

        foreach ($subMenus as $subMenu => $actions) {
            $this->addSql(
                'INSERT INTO sub_menu (menu_id, label)
                VALUE ((SELECT id FROM menu WHERE label = :menu), :subMenu)',
                [
                    'subMenu' => $subMenu,
                    'menu' => Menu::REFERENTIEL,
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
                        WHERE sub_menu.label = :subMenu AND menu.label = :menu
                        LIMIT 1
                    )
                    WHERE action_menu.label = :menu
                      AND action.label = :action',
                    [
                        'menu' => Menu::REFERENTIEL,
                        'subMenu' => $subMenu,
                        'action' => $action
                    ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
