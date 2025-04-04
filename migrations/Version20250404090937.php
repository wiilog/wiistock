<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250404090937 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {

        $this->addSql('INSERT INTO menu (label) VALUES (:label)', [
            "label" => Menu::GENERAL,
        ]);

        $actionsToCreate = [Action::SHOW_CART, Action::SHOW_LANGUAGES, Action::SHOW_NOTIFICATIONS, Action::RECEIVE_EMAIL_ON_NEW_USER];

        $allRoleId =  $this->connection->fetchAllAssociative('SELECT id FROM role WHERE label != :label', [
            'label' => ROle::NO_ACCESS_USER,
        ]);

        $allRoleIsMailSendAccountCreationId =  $this->connection->fetchAllAssociative(
            'SELECT id FROM role WHERE label != :label AND is_mail_send_account_creation = true',
            [
            'label' => Role::NO_ACCESS_USER,
            ]
        );

        foreach ($actionsToCreate as $actionToCreate) {

            $this->addSql('INSERT INTO action (label, menu_id) VALUES (:actionLabel, (SELECT id FROM menu WHERE label = :menuLabel LIMIT 1))', [
                'actionLabel' => $actionToCreate,
                'menuLabel' => Menu::GENERAL,
            ]);

            $rolesToGiveAction = $actionToCreate === Action::RECEIVE_EMAIL_ON_NEW_USER
                ? $allRoleIsMailSendAccountCreationId
                : $allRoleId;

            foreach ($rolesToGiveAction ?: [] as $roleToGiveAction) {
                $this->addSql('INSERT INTO action_role (action_id, role_id) VALUES ((SELECT id FROM action WHERE label = :actionLabel LIMIT 1), :roleId)', [
                    'actionLabel' => $actionToCreate,
                    'roleId' => $roleToGiveAction["id"],
                ]);
            }
        }
    }

    public function down(Schema $schema): void {}
}
