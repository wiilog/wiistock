<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Action;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211125152532 extends AbstractMigration {

    public function up(Schema $schema): void
    {
        $demande = $this->connection->executeQuery("SELECT id FROM menu WHERE label = 'demande'")->fetchOne();
        $previousActions = Stream::from($this->connection->executeQuery("SELECT id FROM action WHERE menu_id = $demande AND label IN ('ajouter colis', 'modifier colis', 'supprimer colis')"))
            ->map(fn(array $row) => $row["id"])
            ->join(",");

        $roles = $this->connection->executeQuery("
            SELECT role.id
            FROM role
                LEFT JOIN action_role ar ON role.id = ar.role_id
                LEFT JOIN action a on ar.action_id = a.id
            WHERE a.menu_id = $demande AND a.id IN (:previousActions)
            GROUP BY role.id", ["previousActions" => $previousActions]);

        $this->addSql("INSERT INTO action(menu_id, label) VALUES (:demande, :label)", [
            "demande" => $demande,
            "label" => Action::MANAGE_PACK,
        ]);

        $newAction = "(SELECT id FROM action WHERE label = '" . Action::MANAGE_PACK . "')";

        foreach($roles as $role) {
            $this->addSql("INSERT INTO action_role(action_id, role_id) VALUES ($newAction, {$role["id"]})");
            $this->addSql("DELETE FROM action_role WHERE action_id IN (:actions)", [
                "actions" => $previousActions
            ]);
        }

        $this->addSql("DELETE FROM action WHERE id IN ($previousActions)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
