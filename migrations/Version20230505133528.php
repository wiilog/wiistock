<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Role;
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
        $role = $this->connection->fetchAssociative('select * from role where label = :label', ['label' => Role::NO_ACCESS_USER]);

        // delete action_role for  "aucun accès" role
        $this->addSql('delete from action_role where role_id = :role_id', ['role_id' => $role['id']]);


        // create new action ACCESS_NOMADE_LOGIN
        $this->addSql('insert into action (label, menu_id, label, sub_menu_id, display_order) values (:label, :menu_id, :label, :sub_menu_id, :display_order)', [
            //TODO
        ]);


    }

    public function down(Schema $schema): void
    {
    }
}
