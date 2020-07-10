<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200709162007 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $duplicateRequestsByNumber = array_reduce(
            $this
                ->connection
                ->executeQuery('
                    SELECT demande.id AS id,
                           demande.numero AS numero
                    FROM demande
                    WHERE demande.numero IN (
                        SELECT numero
                        FROM demande
                        GROUP BY demande.numero
                        HAVING COUNT(demande.numero) > 1
                    )
                ')
                ->fetchAll(),
            function ($acc, $demande) {
                if (!isset($acc[$demande['numero']])) {
                    $acc[$demande['numero']] = [];
                }
                $acc[$demande['numero']][] = $demande;
                return $acc;
            },
            []);

        foreach ($duplicateRequestsByNumber as $number => $preparation) {
            foreach ($preparation as $index => $preparation) {
                $cpt = sprintf('%02u', $index + 1);
                $newNumber = $number . '-' . $cpt;
                $id = $preparation['id'];
                $this->addSql("UPDATE demande SET numero = '$newNumber' WHERE id = ${id}");
            }
        }
        $duplicatePreparationByNumber = array_reduce(
            $this
                ->connection
                ->executeQuery('
                    SELECT preparation.id AS id,
                           preparation.numero AS numero
                    FROM preparation
                    WHERE preparation.numero IN (
                        SELECT numero
                        FROM preparation
                        GROUP BY preparation.numero
                        HAVING COUNT(preparation.numero) > 1
                    )
                ')
                ->fetchAll(),
            function ($acc, $preparation) {
                if (!isset($acc[$preparation['numero']])) {
                    $acc[$preparation['numero']] = [];
                }
                $acc[$preparation['numero']][] = $preparation;
                return $acc;
            },
            []);

        foreach ($duplicatePreparationByNumber as $number => $preparations) {
            foreach ($preparations as $index => $preparation) {
                $cpt = sprintf('%02u', $index + 1);
                $newNumber = $number . '-' . $cpt;
                $id = $preparation['id'];
                $this->addSql("UPDATE preparation SET numero = '$newNumber' WHERE id = ${id}");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    }
}
