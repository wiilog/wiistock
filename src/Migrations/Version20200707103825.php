<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200707103825 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $duplicatePacksWithoutArrival = $this->connection
            ->executeQuery('
                SELECT colis.id AS colisId,
                       colis.code AS colisCode,
                       colis.last_drop_id AS last_drop_id
                FROM colis
                WHERE colis.code IN (
                    SELECT colis_doublons.code AS code_colis from colis colis_doublons
                    GROUP BY code_colis
                    HAVING COUNT(code_colis) > 1)
                  AND colis.arrivage_id IS NULL
                order by colisCode
            ')
            ->fetchAll();
        $duplicatePacksWithArrival = array_reduce(
            $this
                ->connection
                ->executeQuery('
                    SELECT colis.id AS colisId,
                           colis.code AS colisCode,
                           arrivage.id AS arrivageId,
                           arrivage.numero_arrivage AS arrivageNumero,
                           colis.last_drop_id AS last_drop_id
                    FROM colis
                        INNER JOIN arrivage ON colis.arrivage_id = arrivage.id
                    WHERE colis.code IN (
                        SELECT colis_doublons.code AS code_colis from colis colis_doublons
                        GROUP BY code_colis
                        HAVING COUNT(code_colis) > 1)
                    ORDER BY colisCode
                ')
                ->fetchAll(),
            function ($acc, $pack) {
                $acc[$pack['colisCode']] = $pack;
                return $acc;
            },
        []);

        foreach ($duplicatePacksWithoutArrival as $pack) {
            $packCode = $pack['colisCode'];
            $packId = $pack['colisId'];
            if (isset($duplicatePacksWithArrival[$packCode])) {
                $arrivalPack = $duplicatePacksWithArrival[$packCode];
                if (empty($arrivalPack['last_drop_id']) && !empty($pack['last_drop_id'])) {
                    $arrivalPackCode = $arrivalPack['colisCode'];
                    $new_last_drop_id = $pack['last_drop_id'];
                    $this->addSql("UPDATE colis SET colis.last_drop_id = ${new_last_drop_id} WHERE colis.code = '${arrivalPackCode}'");
                }

                $this->addSql("DELETE FROM `colis` WHERE colis.id = ${packId}");
            }
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
