<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\FiltreSup;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200902093337 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE manutention RENAME TO handling');
        $this->addSql("UPDATE categorie_statut SET nom = '" . CategorieStatut::HANDLING . "' WHERE nom = 'manutention'");
        $this->addSql("UPDATE filtre_sup SET page = '" . FiltreSup::PAGE_HAND . "' WHERE page = 'manutention'");
        $this->addSql("UPDATE action SET label = '" . Action::DISPLAY_HAND . "' WHERE label = 'afficher manutentions'");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
