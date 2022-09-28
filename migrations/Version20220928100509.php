<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220928100509 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM setting WHERE label = 'FTP_ROUND_%'");
    }

}
