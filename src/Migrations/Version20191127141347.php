<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191127141347 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE acheminements (id INT AUTO_INCREMENT NOT NULL, receiver_id INT NOT NULL, requester_id INT NOT NULL, statut_id INT NOT NULL, date DATETIME NOT NULL, colis JSON DEFAULT NULL, location_take VARCHAR(64) NOT NULL, location_drop VARCHAR(64) NOT NULL, INDEX IDX_1270F949CD53EDB6 (receiver_id), INDEX IDX_1270F949ED442CF4 (requester_id), INDEX IDX_1270F949F6203804 (statut_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reception_traca (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, arrivage VARCHAR(255) DEFAULT NULL, number VARCHAR(255) DEFAULT NULL, date_creation DATETIME DEFAULT NULL, INDEX IDX_5C71BDD0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE days_worked (id INT AUTO_INCREMENT NOT NULL, day VARCHAR(255) DEFAULT NULL, worked TINYINT(1) NOT NULL, times VARCHAR(255) DEFAULT NULL, display_order INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nature (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE urgence (id INT AUTO_INCREMENT NOT NULL, date_start DATETIME NOT NULL, date_end DATETIME NOT NULL, commande VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE acheminements ADD CONSTRAINT FK_1270F949CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE acheminements ADD CONSTRAINT FK_1270F949ED442CF4 FOREIGN KEY (requester_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE acheminements ADD CONSTRAINT FK_1270F949F6203804 FOREIGN KEY (statut_id) REFERENCES statut (id)');
        $this->addSql('ALTER TABLE reception_traca ADD CONSTRAINT FK_5C71BDD0A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('DROP TABLE article_ordre_collecte');
        $this->addSql('DROP TABLE ordre_collecte_reference');
        $this->addSql('ALTER TABLE arrivage ADD is_urgent TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE emplacement ADD date_max_time VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE colis ADD nature_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE colis ADD CONSTRAINT FK_470BDFF93BCB2E4B FOREIGN KEY (nature_id) REFERENCES nature (id)');
        $this->addSql('CREATE INDEX IDX_470BDFF93BCB2E4B ON colis (nature_id)');
        $this->addSql('ALTER TABLE piece_jointe ADD mvt_traca_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE piece_jointe ADD CONSTRAINT FK_AB5111D4DDBB9B32 FOREIGN KEY (mvt_traca_id) REFERENCES mouvement_traca (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_AB5111D4DDBB9B32 ON piece_jointe (mvt_traca_id)');
        $this->addSql('ALTER TABLE mouvement_traca ADD emplacement_id INT DEFAULT NULL, ADD type_id INT DEFAULT NULL, ADD operateur_id INT DEFAULT NULL, ADD colis VARCHAR(255) DEFAULT NULL, ADD unique_id_for_mobile VARCHAR(255) DEFAULT NULL, ADD datetime DATETIME DEFAULT NULL, ADD commentaire LONGTEXT DEFAULT NULL, DROP ref_article, DROP date, DROP ref_emplacement, DROP type, DROP operateur');
        $this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT FK_1CE28F33C4598A51 FOREIGN KEY (emplacement_id) REFERENCES emplacement (id)');
        $this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT FK_1CE28F33C54C8C93 FOREIGN KEY (type_id) REFERENCES statut (id)');
        $this->addSql('ALTER TABLE mouvement_traca ADD CONSTRAINT FK_1CE28F333F192FC FOREIGN KEY (operateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_1CE28F33C4598A51 ON mouvement_traca (emplacement_id)');
        $this->addSql('CREATE INDEX IDX_1CE28F33C54C8C93 ON mouvement_traca (type_id)');
        $this->addSql('CREATE INDEX IDX_1CE28F333F192FC ON mouvement_traca (operateur_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE colis DROP FOREIGN KEY FK_470BDFF93BCB2E4B');
        $this->addSql('CREATE TABLE article_ordre_collecte (article_id INT NOT NULL, ordre_collecte_id INT NOT NULL, INDEX IDX_50D26FDD7294869C (article_id), INDEX IDX_50D26FDDA0B3D36E (ordre_collecte_id), PRIMARY KEY(article_id, ordre_collecte_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ordre_collecte_reference (id INT AUTO_INCREMENT NOT NULL, ordre_collecte_id INT DEFAULT NULL, reference_article_id INT DEFAULT NULL, quantite INT DEFAULT NULL, INDEX IDX_24882985268AB3D3 (reference_article_id), INDEX IDX_24882985A0B3D36E (ordre_collecte_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE article_ordre_collecte ADD CONSTRAINT FK_50D26FDD7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_ordre_collecte ADD CONSTRAINT FK_50D26FDDA0B3D36E FOREIGN KEY (ordre_collecte_id) REFERENCES ordre_collecte (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ordre_collecte_reference ADD CONSTRAINT FK_24882985268AB3D3 FOREIGN KEY (reference_article_id) REFERENCES reference_article (id)');
        $this->addSql('ALTER TABLE ordre_collecte_reference ADD CONSTRAINT FK_24882985A0B3D36E FOREIGN KEY (ordre_collecte_id) REFERENCES ordre_collecte (id)');
        $this->addSql('DROP TABLE acheminements');
        $this->addSql('DROP TABLE reception_traca');
        $this->addSql('DROP TABLE days_worked');
        $this->addSql('DROP TABLE nature');
        $this->addSql('DROP TABLE urgence');
        $this->addSql('ALTER TABLE arrivage DROP is_urgent');
        $this->addSql('DROP INDEX IDX_470BDFF93BCB2E4B ON colis');
        $this->addSql('ALTER TABLE colis DROP nature_id');
        $this->addSql('ALTER TABLE emplacement DROP date_max_time');
        $this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY FK_1CE28F33C4598A51');
        $this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY FK_1CE28F33C54C8C93');
        $this->addSql('ALTER TABLE mouvement_traca DROP FOREIGN KEY FK_1CE28F333F192FC');
        $this->addSql('DROP INDEX IDX_1CE28F33C4598A51 ON mouvement_traca');
        $this->addSql('DROP INDEX IDX_1CE28F33C54C8C93 ON mouvement_traca');
        $this->addSql('DROP INDEX IDX_1CE28F333F192FC ON mouvement_traca');
        $this->addSql('ALTER TABLE mouvement_traca ADD ref_article VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD date VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ref_emplacement VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD type VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD operateur VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, DROP emplacement_id, DROP type_id, DROP operateur_id, DROP colis, DROP unique_id_for_mobile, DROP datetime, DROP commentaire');
        $this->addSql('ALTER TABLE piece_jointe DROP FOREIGN KEY FK_AB5111D4DDBB9B32');
        $this->addSql('DROP INDEX IDX_AB5111D4DDBB9B32 ON piece_jointe');
        $this->addSql('ALTER TABLE piece_jointe DROP mvt_traca_id');
    }
}
