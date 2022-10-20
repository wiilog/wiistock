<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220510143352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
      /*
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('INSERT INTO action (menu_id, label, sub_menu_id, display_order) VALUES (
            (SELECT menu.id FROM menu WHERE menu.label = :menu),
            :action,
            (SELECT sub_menu.id FROM sub_menu INNER JOIN menu m on sub_menu.menu_id = m.id WHERE sub_menu.label = :sub_menu AND m.label = :menu),
            5
        )', [
            'menu' => Menu::REFERENTIEL,
            'action' => Action::DISPLAY_PACK_NATURE,
            'sub_menu' => ActionsFixtures::SUB_MENU_PAGE,
        ]);
        $existingRoles = $this->connection
            ->executeQuery("SELECT id FROM role where role.label <> :aucun_access", [
                'aucun_access' => Role::NO_ACCESS_USER
            ])
            ->fetchAllAssociative();
        foreach ($existingRoles as $role) {
            $roleId = $role['id'];
            // this up() migration is auto-generated, please modify it to your needs
            $this->addSql('INSERT INTO action_role (action_id, role_id) VALUES (
                (SELECT id FROM action WHERE action.label = :action),
                :role
            )', [
                'action' => Action::DISPLAY_PACK_NATURE,
                'role' => $roleId,
            ]);
        }*/
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
