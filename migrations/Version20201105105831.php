<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201105105831 extends AbstractMigration {

    public function up(Schema $schema): void {
        $filters = $this->connection->executeQuery("SELECT id, champ_fixe FROM filtre_ref");

        foreach($filters as $filter) {
            if($filter["champ_fixe"] == "Statut") {
                $this->addSql("UPDATE filtre_ref SET champ_fixe = 'status' WHERE id = " . $filter["id"]);
            }
        }
    }

}
