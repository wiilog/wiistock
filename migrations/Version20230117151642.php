<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230117151642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // get all the transport_request that have the same number and make the unique
        $requests = $this->connection
            ->executeQuery("
                SELECT id
                FROM transport_request
                WHERE number IN (
                    SELECT number
                    FROM transport_request
                    GROUP BY number
                    HAVING COUNT(*) > 1)
            ")->fetchAllAssociative();

        foreach ($requests as $index => $request) {
            $this->addSql("UPDATE transport_request SET number = CONCAT(number, '-', :index) WHERE id = :id", [
                "index" => $index + 1,
                "id" => $request['id']
            ]);
        }
    }
}
