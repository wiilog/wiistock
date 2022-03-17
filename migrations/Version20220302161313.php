<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Action;
use App\Entity\Menu;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220302161313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $adminRoles = $this->connection
            ->executeQuery("
                SELECT role_id AS id
                FROM action_role
                    INNER JOIN action ON action_role.action_id = action.id
                    INNER JOIN menu on action.menu_id = menu.id
                WHERE action.label LIKE :action
                  AND menu.label LIKE :menu
            ", [
                "action" => Action::SETTINGS_GLOBAL,
                'menu' => Menu::PARAM
            ])
            ->fetchAllAssociative();

        $newActions = [
            Action::SETTINGS_STOCK,
            Action::SETTINGS_TRACING,
            Action::SETTINGS_TRACKING,
            Action::SETTINGS_MOBILE,
            Action::SETTINGS_DASHBOARDS,
            Action::SETTINGS_IOT,
            Action::SETTINGS_NOTIFICATIONS,
            Action::SETTINGS_USERS,
            Action::SETTINGS_DASHBOARDS,
            Action::SETTINGS_DATA
        ];

        foreach ($newActions as $action) {
            $existingId = $this->connection
                ->executeQuery("
                    SELECT action.id AS id
                    FROM action
                        INNER JOIN menu on action.menu_id = menu.id
                    WHERE action.label = :action
                      AND menu.label = :menu
                ", [
                    "action" => $action,
                    'menu' => Menu::PARAM
                ])
                ->fetchOne();

            if (!$existingId) {
                $this->addSql("INSERT INTO action (menu_id, label) VALUE (
                    (SELECT id FROM menu WHERE menu.label = :menu),
                    :action
                )", [
                    'menu' => Menu::PARAM,
                    'action' => $action
                ]);
                foreach ($adminRoles as $role) {
                    $roleId = $role['id'];
                    $this->addSql("
                        INSERT INTO action_role (action_id, role_id) VALUE (
                            (
                                SELECT action.id AS id
                                FROM action
                                    INNER JOIN menu on action.menu_id = menu.id
                                WHERE action.label = :action
                                  AND menu.label = :menu
                            ),
                            :role_id
                        )
                    ", [
                        "action" => $action,
                        'menu' => Menu::PARAM,
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
