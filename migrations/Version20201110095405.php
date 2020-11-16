<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201110095405 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $sqlRequests = [
            'dispatch' => [
                'number' => 'number',
                'order' => 'creation_date'
            ],
            'demande' => [
                'number' => 'numero',
                'order' => 'date'
            ],
            'collecte' => [
                'number' => 'numero',
                'order' => 'date'
            ],
            'ordre_collecte' => [
                'number' => 'numero',
                'order' => 'date'
            ],
            'reception' => [
                'number' => 'numero_reception',
                'order' => 'date'
            ],
            'transfer_request' => [
                'number' => 'number',
                'order' => 'creation_date'
            ],
            'transfer_order' => [
                'number' => 'number',
                'order' => 'creation_date'
            ],
            'handling' => [
                'number' => 'number',
                'order' => 'creation_date'
            ]
        ];

        foreach ($sqlRequests as $table => $request) {
            $numberLabel = $request['number'];
            $orderLabel = $request['order'];

            $results = $this->connection
                ->executeQuery("
                    SELECT id, ${numberLabel} AS number
                    FROM ${table}
                    WHERE ${numberLabel} IN (SELECT duplicate.${numberLabel} FROM ${table} AS duplicate GROUP BY duplicate.${numberLabel} HAVING COUNT(id) > 1)
                    ORDER BY ${orderLabel}
                ")
                ->fetchAll();

            if (!empty($results)) {
                $lastNumber = null;
                $lastIndex = null;
                foreach ($results as $row) {
                    $currentNumber = $row['number'];
                    $currentId = $row['id'];

                    // we reset counter if we have change number
                    if (!isset($lastNumber) || $lastNumber !== $currentNumber) {
                        $lastNumber = $currentNumber;
                        $lastIndex = 0;
                    }

                    $lastIndex++;

                    $suffix = sprintf('%02u', $lastIndex);
                    $newNumber = $currentNumber . '-' . $suffix;
                    $this->addSql("
                        UPDATE ${table}
                        SET ${numberLabel} = '${newNumber}'
                        WHERE id = ${currentId}
                    ");
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
