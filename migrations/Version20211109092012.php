<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

final class Version20211109092012 extends AbstractMigration {

    public function up(Schema $schema): void {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable("demande")->hasColumn("created_at")) {
            $this->addSql("ALTER TABLE demande RENAME COLUMN date TO created_at");
        }

        if(!$schema->getTable("demande")->hasColumn("validated_at")) {
            $this->addSql("ALTER TABLE demande ADD validated_at DATETIME DEFAULT NULL");
        }

        $users = $this->connection->executeQuery("SELECT id FROM utilisateur")->fetchAllAssociative();

        $previousDateFieldName = 'date';
        $newDateFieldName = 'createdAt';
        foreach($users as $user) {
            $visibleColumns = $this->connection->executeQuery("SELECT visible_columns FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn();
            $visibleColumns = json_decode($visibleColumns[0], true);

            $visibleColumns['deliveryRequest'] = Stream::from($visibleColumns['deliveryRequest'])
                ->map(fn($value) => $value == $previousDateFieldName ? $newDateFieldName : $value)
                ->toArray();

            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = ${user['id']}", ['columns' => json_encode($visibleColumns)]);
        }

        $deliveryRequests = $this->connection
            ->executeQuery("SELECT demande.id, MIN(preparation.date) AS preparation_date FROM demande LEFT JOIN preparation ON demande.id = preparation.demande_id WHERE preparation.date IS NOT NULL GROUP BY demande.id")
            ->fetchAllAssociative();

        foreach($deliveryRequests as $deliveryRequest) {
            $this->addSql("UPDATE demande SET validated_at = '${deliveryRequest['preparation_date']}' WHERE id = ${deliveryRequest['id']}");
        }
    }

}
