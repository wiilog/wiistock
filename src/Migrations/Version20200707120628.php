<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200707120628 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $duplicatePacks = array_reduce(
            $this
                ->connection
                ->executeQuery('
                    SELECT colis.id AS colisId,
                           colis.code AS colisCode,
                           arrivage.id AS arrivageId,
                           arrivage.numero_arrivage AS arrivageNumero,
                           colis.last_drop_id
                    FROM colis
                        INNER JOIN arrivage ON colis.arrivage_id = arrivage.id
                    WHERE colis.code IN (
                        SELECT colis_doublons.code AS code_colis from colis colis_doublons
                        INNER JOIN arrivage ON colis_doublons.arrivage_id = arrivage.id
                        GROUP BY code_colis
                        HAVING COUNT(code_colis) > 1)
                    ORDER BY colisCode
                ')
                ->fetchAll(),
                function ($acc, $pack) {
                    $numeroArrivage = $pack['arrivageNumero'];
                    $arrivageId = $pack['arrivageId'];
                    if (!isset($acc[$numeroArrivage])) {
                        $acc[$numeroArrivage] = [];
                    }
                    if (!isset($acc[$numeroArrivage][$arrivageId])) {
                        $acc[$numeroArrivage][$arrivageId] = [];
                    }
                    $acc[$numeroArrivage][$arrivageId][] = $pack;
                    return $acc;
                },
            []);


        foreach ($duplicatePacks as $numArrivage => $packByArrivageId) {
            foreach ($packByArrivageId as $arrivageId => $packs) {
                preg_match('/[^-]+-([^-]+)/', $numArrivage, $matches);
                foreach ($packs as $pack) {
                    $packId = $pack['colisId'];
                    $newCode = $pack['colisCode'] . '-' . $matches[1];
                    $this->addSql("UPDATE colis SET colis.code = '${newCode}' WHERE colis.id = ${packId}");
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
