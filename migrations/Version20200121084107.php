<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200121084107 extends AbstractMigration
{

    public function getDescription() : string
    {
        return 'Set quantite_prelevee pour les anciennes demandes.';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("
            UPDATE article
                INNER JOIN demande d on article.demande_id = d.id
            SET article.quantite_prelevee = article.quantite_aprelever
            WHERE article.quantite_aprelever IS NOT NULL
              AND article.quantite_prelevee IS NULL
        ");
        $this->addSql("
            UPDATE ligne_article
            SET ligne_article.quantite_prelevee = ligne_article.quantite
            WHERE ligne_article.quantite IS NOT NULL
              AND ligne_article.to_split IS NULL
              AND ligne_article.quantite_prelevee IS NULL
        ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
