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
final class Version20200826214801 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $displayAcheLabel = Action::DISPLAY_ACHE;
        $tracaMenuLabel = Menu::TRACA;
        $demMenuLabel = Menu::DEM;

        $res = $this->connection
            ->executeQuery("
                SELECT action.id AS id
                FROM action
                INNER JOIN menu ON action.menu_id = menu.id
                WHERE action.label = '${displayAcheLabel}'
                  AND menu.label = '${tracaMenuLabel}'")
            ->fetchAll();
        $oldActionId = !empty($res) ? $res[0]['id'] : null;

        if (!empty($oldActionId)) {
            $this->connection->executeQuery("INSERT INTO action (menu_id, label) VALUES ((SELECT id FROM menu WHERE menu.label = '${demMenuLabel}' LIMIT 1), 'afficher acheminements')");
            $newActionId = $this->connection->lastInsertId();

            if (!empty($newActionId)) {
                $this->addSql("UPDATE action_role SET action_id = ${newActionId} WHERE action_id = ${oldActionId}");
            }

            $this->addSql("DELETE FROM action WHERE id = ${oldActionId}");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
