<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201102163706 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("INSERT INTO parametrage_global(label, value) VALUES ('WEBSITE_LOGO', 'img/followGTwhite.svg')");
        $this->addSql("INSERT INTO parametrage_global(label, value) VALUES ('EMAIL_LOGO', 'img/gtlogistics.jpg')");
        $this->addSql("INSERT INTO parametrage_global(label, value) VALUES ('MOBILE_LOGO', 'img/mobile_logo.svg')");
    }

}
