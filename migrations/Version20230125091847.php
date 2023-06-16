<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Zone;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230125091847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->hasTable('zone')){
            $this->addSql('CREATE TABLE zone (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, inventory_indicator DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE emplacement ADD zone_id INT DEFAULT NULL');
        }

        $this->addSql('INSERT INTO zone(name, description) VALUES (:standardActivityZoneName, :standardActivityZoneName);', [
            'standardActivityZoneName' => Zone::ACTIVITY_STANDARD_ZONE_NAME
        ]);
        $this->addSql('UPDATE emplacement SET zone_id = LAST_INSERT_ID() WHERE zone_id IS NULL;');
    }

    public function down(Schema $schema): void {}
}
