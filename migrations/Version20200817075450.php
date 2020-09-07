<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Dispatch;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200817075450 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {

        $this->connection->executeQuery("
                ALTER TABLE pack ADD treated TINYINT(1) DEFAULT '1' NOT NULL;
                ALTER TABLE acheminements ADD type_id INT DEFAULT NULL;
        ");
        $this->connection->executeQuery("INSERT INTO category_type (label)  VALUES ('acheminements')");
        $categoryID = $this->connection->lastInsertId();
        $this->connection->executeQuery("INSERT INTO type (category_id, label)  VALUES (${categoryID}, 'standard')");
        $typeID = $this->connection->lastInsertId();
        $this->connection->executeQuery("
                UPDATE acheminements SET type_id = ${typeID}
        ");

        $this
            ->connection->executeQuery("CREATE TABLE pack_acheminement
                                              (id INT AUTO_INCREMENT NOT NULL,
                                              pack_id INT NOT NULL,
                                              acheminement_id INT NOT NULL,
                                              quantity INT NOT NULL,
                                              INDEX IDX_3781F5A11919B217 (pack_id),
                                              INDEX IDX_3781F5A16BB47450 (acheminement_id),
                                              PRIMARY KEY(id))
                                              DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");

        $this
            ->connection->executeQuery("
                ALTER TABLE acheminements ADD location_from_id INT DEFAULT NULL, ADD location_to_id INT DEFAULT NULL;");

        // this up() migration is auto-generated, please modify it to your needs
        $allDispatches = $this
                                ->connection
                                ->executeQuery("
                                    SELECT acheminements.id,
                                           location_drop,
                                           location_take,
                                           packs,
                                           s.nom as statut,
                                           requester_id,
                                           date
                                    FROM acheminements
                                    INNER JOIN statut s on acheminements.statut_id = s.id
                                ")->fetchAll();

        foreach ($allDispatches as $dispatch) {
            $dispatchId = $dispatch['id'];
            $locationTo = $dispatch['location_drop'];
            $locationFrom = $dispatch['location_take'];
            $packs = json_decode($dispatch['packs']);
            $locationToID = $this
                ->connection
                ->executeQuery("SELECT id FROM emplacement WHERE label = '${locationTo}'")->fetchColumn();
            if (!$locationToID) {
                $this
                    ->connection
                    ->executeQuery("
                            INSERT INTO emplacement (label, description, is_delivery_point, date_max_time, is_active)
                            VALUES ('${locationTo}', '${locationTo}', 0, NULL, 1)
                            ");
                $locationToID = intval($this->connection->lastInsertId());
            }
            $locationFromID = $this
                ->connection
                ->executeQuery("SELECT id FROM emplacement WHERE label = '${locationFrom}'")->fetchColumn();
            if (!$locationFromID) {
                $this
                    ->connection
                    ->executeQuery("
                            INSERT INTO emplacement (label, description, is_delivery_point, date_max_time, is_active)
                            VALUES ('${locationFrom}', '${locationFrom}', 0, NULL, 1)
                            ");
                $locationFromID = intval($this->connection->lastInsertId());
            }
            $this
                ->connection
                ->executeQuery("
                    UPDATE acheminements SET location_from_id = ${locationFromID}, location_to_id = ${locationToID} WHERE id = ${dispatchId}
                ");
            foreach ($packs as $pack) {
                $packID = $this->connection->executeQuery("SELECT id FROM pack WHERE code = '${pack}'")->fetchColumn();
                if (!$packID) {
                    $this
                        ->connection
                        ->executeQuery("INSERT INTO pack (code) VALUES ('${pack}')");
                    $packID = intval($this->connection->lastInsertId());
                }

                $this
                    ->connection
                    ->executeQuery("
                        INSERT INTO pack_acheminement (pack_id, acheminement_id, quantity)
                        VALUES (${packID}, ${dispatchId}, 1)
                    ");
            }
        }
        $this->connection->executeQuery('ALTER TABLE acheminements DROP location_take');
        $this->connection->executeQuery('ALTER TABLE acheminements DROP location_drop');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
