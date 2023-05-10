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
        // find "aucun accès" role
        $rightNoAccess = $this->connection->fetchAssociative('select * from role where label = :label', [
            'label' => Role::NO_ACCESS_USER,
        ]);

        // delete action_role for  "aucun accès" role
        $this->addSql('delete from action_role where role_id = :role_id', [
            'role_id' => $rightNoAccess['id'],
        ]);

        $menuIdNomade = $this->connection->fetchAssociative('select id from menu where label = :label', ['label' => Menu::NOMADE]);

        // create new action ACCESS_NOMADE_LOGIN
        $this->addSql('insert into action (label, menu_id, sub_menu_id, display_order) values (:label, :menu_id, :sub_menu_id, :display_order)', [
            'label' => Action::ACCESS_NOMADE_LOGIN,
            // find menu_id of with label 'nomade'
            'menu_id' => $menuIdNomade,
            'sub_menu_id' => $this->connection->fetchAssociative('select id from sub_menu where menu_id = :menu_id and label = :label', [
                'menu_id' => $menuIdNomade,
                'label' => ActionsFixtures::SUB_MENU_GENERAL,
            ]),
            'display_order' => '10',
        ]);

        // get all roles without 'no access'
        $allRolesId = $this->connection->fetchAssociative('select id from role where id != :idRightNoAccess',[
            'idRightNoAccess'=> $rightNoAccess['id'],
        ]);
        dump($allRolesId);
        //foreach roles without 'NoAccessRight' add 3 action : 'accès stock','accès traçabilité' and 'accès demande'
        foreach ($allRolesId as $roleId){
            //add action_role 'accès stock'
            $this->addSql('insert into action_role(action_id, role_id) values(:action_id, :role_id)', [
                'action_id' => $this->connection->fetchAssociative('select id from action where label = :label', ['label' => Action::MODULE_ACCESS_STOCK]) ,
                'role_id' => $roleId,
            ]);

            //add action_role 'accès traçabilité'
            $this->addSql('insert into action_role(action_id, role_id) values(:action_id, :role_id)', [
                'action_id' => $this->connection->fetchAssociative('select id from action where label = :label', ['label' => Action::MODULE_ACCESS_TRACA]) ,
                'role_id' => $roleId,
            ]);

            //add action_role 'accès demande'
            $this->addSql('insert into action_role(action_id, role_id) values(:action_id, :role_id)', [
                'action_id' => $this->connection->fetchAssociative('select id from action where label = :label', ['label' => Action::MODULE_ACCESS_HAND]) ,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
