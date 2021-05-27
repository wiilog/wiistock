<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategoryType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;
use WiiCommon\Helper\Stream;
use App\Service\SpecificService;

final class Version20210511103955 extends AbstractMigration {

    private $specificService;

    public function __construct(Connection $connection, LoggerInterface $logger, SpecificService $specificService)
    {
        parent::__construct($connection, $logger);
        $this->specificService = $specificService;
    }

    public function up(Schema $schema): void {
        if($this->specificService->getAppClient() !== SpecificService::CLIENT_CEA_LETI) {
            $types = $this->connection->executeQuery("SELECT t.id, c.label FROM type t INNER JOIN category_type c ON t.category_id = c.id");
            $locations = Stream::from($this->connection->executeQuery("SELECT id FROM emplacement"))
                ->map(fn(array $location) => $location["id"])
                ->toArray();

            $this->addSql("create table if not exists location_allowed_delivery_type(emplacement_id int not null, type_id int not null, primary key (emplacement_id, type_id))");
            $this->addSql("create table if not exists location_allowed_collect_type(emplacement_id int not null, type_id int not null, primary key (emplacement_id, type_id))");

            foreach ($types as $type) {
                if ($type["label"] === CategoryType::DEMANDE_LIVRAISON) {
                    foreach ($locations as $location) {
                        $this->addSql("INSERT INTO location_allowed_delivery_type(emplacement_id, type_id) VALUES($location, {$type['id']})");
                    }
                } else if ($type["label"] === CategoryType::DEMANDE_COLLECTE) {
                    foreach ($locations as $location) {
                        $this->addSql("INSERT INTO location_allowed_collect_type(emplacement_id, type_id) VALUES($location, {$type['id']})");
                    }
                }
            }
        }
    }
}
