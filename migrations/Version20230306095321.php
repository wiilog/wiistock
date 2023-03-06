<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Controller\Settings\SettingsController;
use App\DataFixtures\ActionsFixtures;
use App\Entity\Action;
use App\Entity\Menu;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230306095321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE sub_menu SET label = :newLabel WHERE label = 'arrivages'",
            [
                "newLabel" => ActionsFixtures::SUB_MENU_ARRIVALS,
            ]
        );

        $this->addSql("UPDATE action SET label = :newLabel WHERE label = 'Afficher arrivages'",
            [
                "newLabel" => 'afficher arrivages UL',
            ]
        );

        $this->addSql("UPDATE action SET label = :newLabel WHERE label = 'modifier arrivage'",
            [
                "newLabel" =>  Action::EDIT_ARRI,
            ]
        );

        $this->addSql("UPDATE action SET label = :newLabel WHERE label = 'supprimer arrivage'",
            [
                "newLabel" =>  Action::DELETE_ARRI,
            ]
        );

        $this->addSql("UPDATE action SET label = :newLabel WHERE label = 'créer arrivage'",
            [
                "newLabel" =>  Action::CREATE_ARRIVAL,
            ]
        );
    }
}
