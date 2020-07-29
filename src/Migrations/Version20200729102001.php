<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200729102001 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE arrivage_valeur_champ_libre DROP FOREIGN KEY FK_3628CC6152CB289C');
        $this->addSql('ALTER TABLE collecte_valeur_champ_libre DROP FOREIGN KEY FK_471B799752CB289C');
        $this->addSql('ALTER TABLE demande_valeur_champ_libre DROP FOREIGN KEY FK_49FA810F52CB289C');
        $this->addSql('ALTER TABLE reception_valeur_champ_libre DROP FOREIGN KEY FK_3A892A1E52CB289C');
        $this->addSql('ALTER TABLE valeur_champ_libre_article DROP FOREIGN KEY FK_B03869C852CB289C');
        $this->addSql('DROP TABLE alerte_expiry');
        $this->addSql('DROP TABLE arrivage_valeur_champ_libre');
        $this->addSql('DROP TABLE collecte_valeur_champ_libre');
        $this->addSql('DROP TABLE column_hidden');
        $this->addSql('DROP TABLE demande_valeur_champ_libre');
        $this->addSql('DROP TABLE param_client');
        $this->addSql('DROP TABLE preparation_article');
        $this->addSql('DROP TABLE reception_valeur_champ_libre');
        $this->addSql('DROP TABLE valeur_champ_libre');
        $this->addSql('DROP TABLE valeur_champ_libre_article');
        $this->addSql('DROP TABLE valeur_champ_libre_reference_article');
    }

    public function down(Schema $schema) : void
    {

    }
}
