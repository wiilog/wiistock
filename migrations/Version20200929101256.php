<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200929101256 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $packsWithSameLastTracking = $this->connection->executeQuery('
            SELECT pack.id AS pack_id_duplicate, mouvement_traca.*
            FROM pack
            INNER JOIN mouvement_traca on pack.last_tracking_id = mouvement_traca.id
            WHERE pack.last_tracking_id IN (
                SELECT last_tracking_id
                FROM pack
                group by last_tracking_id
                having count(pack.id) > 1 and last_tracking_id is not null
                ORDER by count(pack.id) DESC
            )
        ')
        ->fetchAll();
        $alreadyViewedTracking = [];
        foreach ($packsWithSameLastTracking as $pack) {
            $trackingId = $pack['id'];
            $packId = $pack['pack_id_duplicate'];
            if (!array_key_exists($trackingId, $alreadyViewedTracking)) {
                $alreadyViewedTracking[$trackingId] = true;
            }
            else {
                unset($pack['id']);
                unset($pack['pack_id_duplicate']);
                $keys = implode(
                    ',',
                    array_map(function ($key) {
                        return "`$key`";
                    }, array_keys($pack))
                );
                $values = implode(
                    ',',
                    array_map(function ($value) {
                        if ($value
                            && ($value !== 'NULL'
                            && $value !== 'null'
                            && !is_numeric($value)
                            && is_string($value))) {
                            $value = str_replace("\\", "\\\\", $value);
                            $value = str_replace("'", "''", $value);
                        }
                        return $value === null ? 'NULL' :
                            (
                                ($value !== 'NULL'
                                    && $value !== 'null'
                                    && !is_numeric($value)
                                    && is_string($value)) ? "'{$value}'" : $value
                            );
                    }, array_values($pack))
                );
                $this->connection
                    ->executeQuery("INSERT INTO mouvement_traca ({$keys}) VALUES ({$values});");
                $this->connection
                    ->executeQuery("
                        UPDATE pack
                        SET pack.last_tracking_id = {$this->connection->lastInsertId()},
                            pack.last_drop_id = {$this->connection->lastInsertId()}
                        WHERE pack.id = {$packId}
                    ");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
