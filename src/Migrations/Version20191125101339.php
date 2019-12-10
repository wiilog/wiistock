<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191125101339 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'transform mouvementTraca fields';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // transforme champ mouvement_traca.operateur en clé étrangère vers la table utilisateur
        $this->addSql('UPDATE mouvement_traca SET operateur = ( SELECT utilisateur.id FROM utilisateur WHERE utilisateur.username LIKE mouvement_traca.operateur )');
        $this->addSql('ALTER TABLE mouvement_traca CHANGE operateur operateur_id int(11)');
        $this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT `mouvement_traca_utilisateur_fk1` FOREIGN KEY (operateur_id) REFERENCES utilisateur(id)');

        // transforme champ mouvement_traca.emplacement en clé étrangère vers la table emplacement
		$this->addSql('UPDATE mouvement_traca SET ref_emplacement = ( SELECT emplacement.id FROM emplacement WHERE emplacement.label LIKE mouvement_traca.ref_emplacement )');
		$this->addSql('ALTER TABLE mouvement_traca CHANGE ref_emplacement emplacement_id int(11)');
		$this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT `mouvement_traca_emplacement_fk1` FOREIGN KEY (emplacement_id) REFERENCES emplacement(id)');

        // transforme champ mouvement_traca.type en clé étrangère vers la table statut
		$this->addSql('UPDATE mouvement_traca SET type = ( SELECT statut.id FROM statut WHERE statut.nom LIKE mouvement_traca.type )');
		$this->addSql('ALTER TABLE mouvement_traca CHANGE `type` type_id int(11)');
		$this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT `mouvement_traca_statut_fk1` FOREIGN KEY (type_id) REFERENCES statut(id)');

        // remplit champ mouvement_traca.datetime avec données champ mouvement_traca.date
		$this->addSql('ALTER TABLE mouvement_traca ADD `datetime` datetime');
		$this->addSql('UPDATE mouvement_traca SET datetime = STR_TO_DATE(REPLACE(LEFT(mouvement_traca.date, 19), "T", " "), "%Y-%m-%d %T")');

		// renomme la table ref_article -> colis
		$this->addSql('ALTER table mouvement_traca CHANGE `ref_article` `colis` varchar(255)');
	}

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

		// annule la transformation champ mouvement_traca.operateur en clé étrangère vers la table utilisateur
		$this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY `mouvement_traca_utilisateur_fk1`');
		$this->addSql('ALTER TABLE mouvement_traca CHANGE operateur operateur varchar(255)');
		$this->addSql('UPDATE mouvement_traca SET operateur = ( SELECT utilisateur.username FROM utilisateur WHERE utilisateur.id = mouvement_traca.operateur )');

		// annule la transformation champ mouvement_traca.emplacement en clé étrangère vers la table emplacement
		$this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY `mouvement_traca_emplacement_fk1`');
		$this->addSql('ALTER TABLE mouvement_traca CHANGE ref_emplacement ref_emplacement varchar(255)');
		$this->addSql('UPDATE mouvement_traca SET ref_emplacement = ( SELECT emplacement.label FROM emplacement WHERE emplacement.id = mouvement_traca.ref_emplacement )');

		// annule la transformation champ mouvement_traca.type en clé étrangère vers la table statut
		$this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY `mouvement_traca_statut_fk1`');
		$this->addSql('ALTER TABLE mouvement_traca CHANGE type type varchar(255)');
		$this->addSql('UPDATE mouvement_traca SET type = ( SELECT statut.nom FROM statut WHERE statut.id = mouvement_traca.type )');

		// supprime champ mouvement_traca.datetime
		$this->addSql('ALTER TABLE mouvement_traca DROP `datetime`');

    }
}
