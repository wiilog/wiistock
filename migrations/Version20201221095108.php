<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201221095108 extends AbstractMigration {

    public function up(Schema $schema): void {
        $this->addSql("DROP TABLE IF EXISTS reception_valeur_champs_libre");
        $this->addSql("DROP TABLE IF EXISTS valeur_champs_libre_article");
        $this->addSql("DROP TABLE IF EXISTS valeur_champs_libre_reference_article");
        $this->addSql("DROP TABLE IF EXISTS valeur_champs_libre");
        $this->addSql("DROP TABLE IF EXISTS filter");
        $this->addSql("DROP TABLE IF EXISTS champs_libre");
        $this->addSql("DROP TABLE IF EXISTS reference_article_tmp");
        $this->addSql("DROP TABLE IF EXISTS alerte");
    }

}
