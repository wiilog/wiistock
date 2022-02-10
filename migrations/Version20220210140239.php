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
        $trackingMenuLabel = Menu::TRACA;
        $createActionLabel = Action::CREATE;

        $createArrivalActionLabel = Action::CREATE_ARRIVAL;
        $createEmergencyActionLabel = Action::CREATE_EMERGENCY;

        $trackingMenuId = $this->connection
            ->executeQuery("SELECT id FROM menu WHERE label = '$trackingMenuLabel';")
            ->fetchOne();

        $createTrackingActionId = $this->connection
            ->executeQuery("SELECT id FROM action WHERE label = '$createActionLabel' AND menu_id = $trackingMenuId;")
            ->fetchOne();

        $createArrivalActionId = $this->connection
            ->executeQuery("SELECT id FROM action WHERE label = '$createArrivalActionLabel' AND menu_id = $trackingMenuId;")
            ->fetchOne();

        $createEmergencyActionId = $this->connection
            ->executeQuery("SELECT id FROM action WHERE label = '$createEmergencyActionLabel' AND menu_id = $trackingMenuId;")
            ->fetchOne();

        $rolesWithCreateActionAndTrackingMenu = $this->connection
            ->executeQuery("SELECT role_id AS id FROM action_role WHERE action_id = $createTrackingActionId;")
            ->fetchAllAssociative();

        foreach ($rolesWithCreateActionAndTrackingMenu as $role) {
            $roleId = $role['id'];
            $this->addSql("INSERT INTO action_role(action_id, role_id) VALUES ($createArrivalActionId, $roleId);");
            $this->addSql("INSERT INTO action_role(action_id, role_id) VALUES ($createEmergencyActionId, $roleId);");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
