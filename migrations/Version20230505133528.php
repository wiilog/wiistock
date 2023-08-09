<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\ActionsFixtures;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\SubMenu;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230505133528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $accessNomadeLoginActionId = $this->connection->fetchAssociative('
            SELECT id
            FROM action
            WHERE label = :label',
            [
                'label' => Action::ACCESS_NOMADE_LOGIN
            ])['id'] ?? null;

        if (!empty($accessNomadeLoginActionId)) {
            return;
        }

        // find "aucun accès" role
        $rightNoAccess = $this->connection->fetchAssociative('
            SELECT *
            FROM role
            WHERE label = :label',
            [
                'label' => Role::NO_ACCESS_USER,
            ]);

        if (empty($rightNoAccess)) {
            return;
        }

        // delete action_role for "aucun accès" role
        $this->addSql('
            DELETE
            FROM action_role
            WHERE role_id = :role_id',
            [
                'role_id' => $rightNoAccess['id'],
            ]);

        $menuIdNomade = $this->connection->fetchAssociative('
            SELECT id
            FROM menu
            WHERE label = :label',
            [
                'label' => Menu::NOMADE
            ])['id'] ?? null;

        if (empty($menuIdNomade)) {
            return;
        }
        $subMenuId = $this->connection->fetchAssociative('
            SELECT id
            FROM sub_menu
            WHERE menu_id = :menu_id
              AND label = :label',
            [
                'menu_id' => $menuIdNomade,
                'label' => ActionsFixtures::SUB_MENU_GENERAL,
            ])['id'] ?? null;

        if (empty($subMenuId)) {
            return;
        }

        // create new action ACCESS_NOMADE_LOGIN
        $this->addSql('INSERT INTO action (label, menu_id, sub_menu_id, display_order) VALUES (:label, :menu_id, :sub_menu_id, :display_order)', [
                'label' => Action::ACCESS_NOMADE_LOGIN,
                'menu_id' => $menuIdNomade,
                'sub_menu_id' => $subMenuId,
                'display_order' => '0',
            ]);
    }

    public function down(Schema $schema): void
    {
    }
}
