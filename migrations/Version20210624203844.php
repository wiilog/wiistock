<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210624203844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('collecte')->hasColumn('sensor_wrapper_id')) {
            $this->addSql('ALTER TABLE collecte CHANGE sensor_wrapper_id triggering_sensor_wrapper_id INT DEFAULT NULL');
        }
        if ($schema->getTable('demande')->hasColumn('sensor_wrapper_id')) {
            $this->addSql('ALTER TABLE demande CHANGE sensor_wrapper_id triggering_sensor_wrapper_id INT DEFAULT NULL');
        }
        if ($schema->getTable('handling')->hasColumn('sensor_wrapper_id')) {
            $this->addSql('ALTER TABLE handling CHANGE sensor_wrapper_id triggering_sensor_wrapper_id INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
    }
}
