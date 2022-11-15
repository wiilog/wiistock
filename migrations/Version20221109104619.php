<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221109104619 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE statut SET code = 'dépose dans UL' WHERE code LIKE 'depose dans UL'");
    }

}
