<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\IOT\IOTService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230727115259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du type de donnée des messages iot en fonction des capteurs';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable("sensor_message")) {
            $profileToDataType = [
                IOTService::INEO_SENS_ACS_TEMP => IOTService::DATA_TYPE_TEMPERATURE,
                IOTService::INEO_SENS_ACS_TEMP_HYGRO => IOTService::DATA_TYPE_TEMPERATURE,
                IOTService::INEO_SENS_ACS_HYGRO => IOTService::DATA_TYPE_HYGROMETRY,
                IOTService::KOOVEA_TAG => IOTService::DATA_TYPE_TEMPERATURE,
                IOTService::KOOVEA_HUB => IOTService::DATA_TYPE_GPS,
                IOTService::INEO_SENS_GPS => IOTService::DATA_TYPE_GPS,
                IOTService::INEO_SENS_ACS_BTN => IOTService::DATA_TYPE_ACTION,
                IOTService::SYMES_ACTION_MULTI => IOTService::DATA_TYPE_ACTION,
                IOTService::SYMES_ACTION_SINGLE => IOTService::DATA_TYPE_ACTION,
                IOTService::DEMO_ACTION => IOTService::DATA_TYPE_ACTION,
                IOTService::DEMO_TEMPERATURE => IOTService::DATA_TYPE_TEMPERATURE,
            ];


            if (!$schema->getTable("sensor_message")->hasColumn("content_type")) {
                $this->addSql("ALTER TABLE sensor_message ADD content_type INT DEFAULT 0");

                foreach ($profileToDataType as $profileName => $dataType) {
                    $sensors = $this->connection->fetchAllAssociative("
                        SELECT id
                        FROM sensor
                        WHERE profile_id = (
                            SELECT id FROM sensor_profile WHERE name = :profileName
                        )", [
                            'profileName' => $profileName
                        ]);

                    foreach ($sensors ?: [] as $sensor) {
                        $this->addSql("
                            UPDATE sensor_message
                            SET content_type = :contentType WHERE sensor_id = :sensorId",
                            [
                                'contentType' => $dataType,
                                'sensorId' => $sensor['id'],
                            ]);
                    }
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
