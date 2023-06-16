<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Action;
use App\Entity\Role;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230616142802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void {
        // find "aucun accès" role
        $rightNoAccess = $this->connection->fetchAssociative('select * from role where label = :label', [
            'label' => Role::NO_ACCESS_USER,
        ]);
        if (empty($rightNoAccess)) {
            return;
        }

        // get all roles without 'no access'
        $allRolesId = $this->connection->fetchAllAssociative('select id from role where id != :idRightNoAccess',[
            'idRightNoAccess'=> $rightNoAccess['id'],
        ]);

        //foreach roles without 'NoAccessRight' add 3 action : 'accès stock','accès traçabilité' and 'accès demande'
        $accessNomadeLoginActionId = $this->connection->fetchAssociative('select id from action where label = :label', ['label' => Action::ACCESS_NOMADE_LOGIN])['id'] ?? null;

        foreach ($allRolesId as $roleId){
            //add action_role ACCESS_NOMADE_LOGIN
            $this->addSql('insert into action_role(action_id, role_id) values(:action_id, :role_id)', [
                'action_id' => $accessNomadeLoginActionId,
                'role_id' => $roleId['id'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
