<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230124092808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if(!$schema->getTable("zone")){
            $locations = $this->connection->executeQuery("SELECT id FROM emplacement WHERE zone_id = null OR zone_id = 0")->fetchAll();

            $this->addSql("INSERT INTO zone (name, description) VALUES ('Activité standard', 'Activité standard')");

            foreach ($locations as $location){
                $this->addSQL("UPDATE emplacement SET zone_id = (SELECT LAST_INSERT_ID()) WHERE id = :locationId", [
                    'locationId' => $location['id']
                ]);
            }
        }
    }

    public function down(Schema $schema): void
    {

    }
}
