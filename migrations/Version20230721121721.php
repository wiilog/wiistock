<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230721121721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reserve_type (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, default_reserve_type TINYINT(1) DEFAULT NULL, active TINYINT(1) DEFAULT 1, UNIQUE INDEX UNIQ_B7802434EA750E8 (label), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reserve_type_utilisateur (reserve_type_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_A8CB84365973EA4D (reserve_type_id), INDEX IDX_A8CB8436FB88E14F (utilisateur_id), PRIMARY KEY(reserve_type_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reserve_type_utilisateur ADD CONSTRAINT FK_A8CB84365973EA4D FOREIGN KEY (reserve_type_id) REFERENCES reserve_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reserve_type_utilisateur ADD CONSTRAINT FK_A8CB8436FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reserve ADD reserve_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reserve ADD CONSTRAINT FK_1FE0EA225973EA4D FOREIGN KEY (reserve_type_id) REFERENCES reserve_type (id)');
        $this->addSql('CREATE INDEX IDX_1FE0EA225973EA4D ON reserve (reserve_type_id)');
    }

    public function down(Schema $schema): void
    {

    }
}
