<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230315112200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable('inventory_mission')->hasColumn('requester_id')) {
            $this->addSql('ALTER TABLE inventory_mission ADD COLUMN requester_id INT DEFAULT NULL');
        }
        $this->addSql('UPDATE inventory_mission SET requester_id = creator_id');
    }


}
