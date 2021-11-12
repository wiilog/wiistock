<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211108093728 extends AbstractMigration {

    public function up(Schema $schema): void {
        if(!$schema->getTable("utilisateur")->hasColumn("visible_columns")) {
            $this->addSql("ALTER TABLE utilisateur ADD visible_columns JSON DEFAULT NULL");
        }

        if(!$schema->getTable("demande")->hasColumn("created_at")) {
            $this->addSql("ALTER TABLE demande RENAME COLUMN date TO created_at");
        }

        if(!$schema->getTable("demande")->hasColumn("validated_at")) {
            $this->addSql("ALTER TABLE demande ADD validated_at DATETIME DEFAULT NULL");
        }

        $users = $this->connection->executeQuery("SELECT id FROM utilisateur")->fetchAllAssociative();

        foreach($users as $user) {
            $visibleColumns = [
                "dispatch" => $this->connection->executeQuery("SELECT columns_visible_for_dispatch FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "dispute" => $this->connection->executeQuery("SELECT columns_visible_for_litige FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "arrival" => $this->connection->executeQuery("SELECT columns_visible_for_arrivage FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "article" => $this->connection->executeQuery("SELECT columns_visible_for_article FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "reference" => $this->connection->executeQuery("SELECT column_visible FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "trackingMovement" => $this->connection->executeQuery("SELECT columns_visible_for_tracking_movement FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "reception" => $this->connection->executeQuery("SELECT columns_visible_for_reception FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
            ];

            $visibleColumns = array_map(fn($value) => json_decode($value[0]), $visibleColumns);
            $visibleColumns["deliveryRequest"] = Utilisateur::DEFAULT_DELIVERY_REQUEST_VISIBLE_COLUMNS;

            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = ${user['id']}", ['columns' => json_encode($visibleColumns)]);
        }

        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_dispatch");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_litige");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_arrivage");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_article");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN column_visible");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_tracking_movement");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_reception");

        $deliveryRequests = $this->connection
            ->executeQuery("SELECT demande.id, MIN(preparation.date) AS preparation_date FROM demande LEFT JOIN preparation ON demande.id = preparation.demande_id WHERE preparation.date IS NOT NULL GROUP BY demande.id")
            ->fetchAllAssociative();

        foreach($deliveryRequests as $deliveryRequest) {
            $this->addSql("UPDATE demande SET validated_at = '${deliveryRequest['preparation_date']}' WHERE id = ${deliveryRequest['id']}");
        }
    }

    public function down(Schema $schema): void {
        // this down() migration is auto-generated, please modify it to your needs

    }

}
