<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200525103526 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE champ_libre
                        SET categorie_cl_id = (SELECT id FROM categorie_cl WHERE label = "reference article")
                        WHERE categorie_cl_id IN (SELECT id FROM categorie_cl WHERE label = "reference CEA")');
        $this->addSql('DELETE FROM categorie_cl
                        WHERE label="reference CEA"');
        $this->addSql('UPDATE type
                        SET category_id = (SELECT id FROM category_type WHERE label = "article")
                        WHERE category_id IN (SELECT id FROM category_type WHERE label = "articles et references CEA")');
        $this->addSql('DELETE FROM category_type
                        WHERE label="articles et références CEA"');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
