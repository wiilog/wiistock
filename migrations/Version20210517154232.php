<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ParametrageGlobal;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210517154232 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $currentDefaultDeliveryLocation = $this->connection->executeQuery("
            SELECT value
            FROM parametrage_global
            WHERE label = '" . ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON . "'
        ")->fetchOne();

        $defaultDeliveryLocation = json_encode(['all' => $currentDefaultDeliveryLocation]);

        $this->addSql("
            UPDATE parametrage_global
            SET value = '". $defaultDeliveryLocation ."'
            WHERE label = '" . ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON . "'");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
