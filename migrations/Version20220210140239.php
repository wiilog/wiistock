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
final class Version20220210140239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $trackingMenuId = $this->connection
            ->executeQuery("SELECT id FROM menu WHERE label = :label", ["label" => Menu::TRACA])
            ->fetchOne();

        $this->addSql('INSERT INTO action (label, menu_id) VALUES (:action, :menu_id)', [
            'action' => Action::CREATE_ARRIVAL,
            'menu_id' => $trackingMenuId,
        ]);
        $this->addSql('INSERT INTO action (label, menu_id) VALUES (:action, :menu_id)', [
            'action' => Action::CREATE_EMERGENCY,
            'menu_id' => $trackingMenuId,
        ]);

        $rolesWithCreateActionAndTrackingMenu = $this->connection
            ->executeQuery("
                SELECT role_id AS id
                FROM action_role
                    INNER JOIN action ON action_role.action_id = action.id
                WHERE action.label = :action
                  AND action.menu_id = :menu_id
            ", ["action" => Action::CREATE, 'menu_id' => $trackingMenuId])
            ->fetchAllAssociative();

        foreach ($rolesWithCreateActionAndTrackingMenu as $role) {
            $actionQuery = '(
                SELECT id
                FROM action
                WHERE action.label = :action AND menu_id = :menu_id
            )';
            $this->addSql("INSERT INTO action_role(action_id, role_id) VALUES ({$actionQuery}, :role_id);", [
                'menu_id' => $trackingMenuId,
                'action' => Action::CREATE_ARRIVAL,
                'role_id' => $role['id']
            ]);
            $this->addSql("INSERT INTO action_role(action_id, role_id) VALUES ({$actionQuery}, :role_id);", [
                'menu_id' => $trackingMenuId,
                'action' => Action::CREATE_EMERGENCY,
                'role_id' => $role['id']
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
