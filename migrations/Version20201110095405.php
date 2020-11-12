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
        $dispatch = $this->connection->executeQuery("
            SELECT number AS number, COUNT(*) AS count
            FROM dispatch
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $deliveryRequest = $this->connection->executeQuery("
            SELECT numero AS number, COUNT(*) AS count
            FROM demande
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $collectRequest = $this->connection->executeQuery("
            SELECT numero AS number, COUNT(*) AS count
            FROM collecte
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $collectOrder = $this->connection->executeQuery("
            SELECT numero AS number, COUNT(*) AS count
            FROM ordre_collecte
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $reception = $this->connection->executeQuery("
            SELECT numero_reception AS number, COUNT(*) AS count
            FROM reception
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $transferRequest = $this->connection->executeQuery("
            SELECT number AS number, COUNT(*) AS count
            FROM transfer_request
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $transferOrder = $this->connection->executeQuery("
            SELECT number AS number, COUNT(*) AS count
            FROM transfer_order
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $handling = $this->connection->executeQuery("
            SELECT number AS number, COUNT(*) AS count
            FROM handling
            GROUP BY number
            HAVING COUNT(*) > 1
        ");

        $sqlRequests = [
            'dispatch' => [
                $dispatch,
                'number',
                'creation_date'
            ],
            'demande' => [
                $deliveryRequest,
                'numero',
                'date'
            ],
            'collecte' => [
                $collectRequest,
                'numero',
                'date'
            ],
            'ordre_collecte' => [
                $collectOrder,
                'numero',
                'ordre'
            ],
            'reception' => [
                $reception,
                'numero_reception',
                'date'
            ],
            'transfer_request' => [
                $transferRequest,
                'number',
                'creation_date'
            ],
            'transfer_order' => [
                $transferOrder,
                'number',
                'creation_date'
            ],
            'handling' => [
                $handling,
                'number',
                'creation_date'
            ]
        ];

        foreach ($sqlRequests as $table => $request) {
            $duplicateNumbers = $request[0]->fetchAll();
            if (!empty($duplicateNumbers)) {
                foreach ($duplicateNumbers as $number) {
                    $index = 1;
                    while ($index < intval($number['count'])) {
                        $currentNumber = $number['number'];
                        $newNumber = $currentNumber . '-0' . $index;
                        $this->addSql("
                            UPDATE ${table}
                            SET ${request[1]} = '${newNumber}'
                            WHERE ${request[1]} = '${currentNumber}'
                            LIMIT 1");
                        $index++;
                    }
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
