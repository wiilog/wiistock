<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

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
            $visibleColumns = $this->connection->executeQuery("
                SELECT columns_visible_for_dispatch AS dispatch,
                       columns_visible_for_litige AS dispute,
                       columns_visible_for_arrivage AS arrival,
                       columns_visible_for_article AS article,
                       column_visible AS reference,
                       columns_visible_for_tracking_movement AS trackingMovement,
                       columns_visible_for_reception AS reception
                FROM utilisateur
                WHERE id = ${user['id']}"
            )
            ->fetchAssociative();

            $defaultVisibleColumns = [
                "dispatch" => Utilisateur::DEFAULT_DISPATCH_VISIBLE_COLUMNS,
                "dispute" => Utilisateur::DEFAULT_DISPUTE_VISIBLE_COLUMNS,
                "arrival" => Utilisateur::DEFAULT_ARRIVAL_VISIBLE_COLUMNS,
                "article" => Utilisateur::DEFAULT_ARTICLE_VISIBLE_COLUMNS,
                "reference" => Utilisateur::DEFAULT_REFERENCE_VISIBLE_COLUMNS,
                "trackingMovement" => Utilisateur::DEFAULT_TRACKING_MOVEMENT_VISIBLE_COLUMNS,
                "reception" => Utilisateur::DEFAULT_RECEPTION_VISIBLE_COLUMNS
            ];

            $visibleColumns = Stream::from($visibleColumns)
                ->keymap(function(?string $value, string $key) use ($defaultVisibleColumns) {
                    $decodedValue = $value ? json_decode($value, true) : null;
                    return [
                        $key,
                        $decodedValue ?: $defaultVisibleColumns[$key]
                    ];
                })
                ->toArray();

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
