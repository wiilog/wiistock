<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Type\CategoryType;
use App\Entity\Emergency\EmergencyDiscrEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use App\Entity\Type\Type;
use DateTime;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250506124113 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        $now = (new DateTime("now"))->format('Y-m-d H:i:s');
        if ($schema->hasTable('emergency')) {
            return;
        }

        $this->addSql('CREATE TABLE arrivage_tracking_emergency (arrivage_id INT NOT NULL, tracking_emergency_id INT NOT NULL, PRIMARY KEY(arrivage_id, tracking_emergency_id))');
        $this->addSql('CREATE TABLE emergency (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, supplier_id INT DEFAULT NULL, buyer_id INT DEFAULT NULL, carrier_id INT DEFAULT NULL, end_emergency_criteria VARCHAR(255) NOT NULL, comment LONGTEXT DEFAULT NULL, carrier_tracking_number VARCHAR(255) DEFAULT NULL, date_start DATETIME DEFAULT NULL, date_end DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, last_triggered_at DATETIME DEFAULT NULL, order_number VARCHAR(255) DEFAULT NULL, free_fields JSON DEFAULT NULL, discr VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE tracking_emergency (id INT NOT NULL, post_number VARCHAR(255) DEFAULT NULL, internal_article_code VARCHAR(255) DEFAULT NULL, supplier_article_code VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_emergency (id INT NOT NULL, expected_location_id INT DEFAULT NULL, reference_article_id INT DEFAULT NULL, emergency_trigger VARCHAR(255) NOT NULL, expected_quantity INT DEFAULT NULL, already_received_quantity INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $trackingEmergencies = $this->connection->fetchAllAssociative('SELECT * FROM urgence');
        $typeIdbyName = [];

        $categoryTypeId = $this->connection->fetchOne(
            "
                        SELECT category_type.id
                        FROM category_type
                        WHERE category_type.label = :categoryTypeLabel
                        LIMIT 1
                    ",
            [
                'categoryTypeLabel' => CategoryType::TRACKING_EMERGENCY,
            ]
        );

        foreach ($trackingEmergencies as $emergency) {
            $type = $emergency['type'] ?: "standard";

            if (!isset($typeIdbyName[$type])) {
                $typeIdbyName[$type] = $this->connection->fetchOne(
                    "
                        SELECT type.id
                        FROM type
                        WHERE type.label = :label
                        AND category_id = :categoryTypeId
                    ",
                    [
                        'label' => $type,
                        'categoryTypeId' => $categoryTypeId,
                    ]
                );
            }
            $typeId = $typeIdbyName[$type];

            $this->addSql(
                "
                    INSERT INTO emergency (
                        id,
                        buyer_id,
                        supplier_id,
                        carrier_id,
                        date_start,
                        date_end,
                        created_at,
                        order_number,
                        carrier_tracking_number,
                        type_id,
                        end_emergency_criteria,
                        discr,
                        comment,
                        closed_at,
                        last_triggered_at,
                        free_fields
                    ) VALUES (
                        :id,
                        :buyerId,
                        :supplierId,
                        :carrierId,
                        :dateStart,
                        :dateEnd,
                        :createdAt,
                        :orderNumber,
                        :carrierTrackingNumber,
                        :typeId,
                        :endEmergencyCriteria,
                        :discr,
                        NULL,
                        NULL,
                        NULL,
                        NULL
                    )
                ",
                [
                    "id" =>  $emergency["id"],
                    "buyerId" => $emergency['buyer_id'],
                    "supplierId" =>  $emergency['provider_id'],
                    "carrierId" => $emergency['carrier_id'],
                    "dateStart" => $emergency['date_start'],
                    "dateEnd" => $emergency['date_end'],
                    "createdAt" => $emergency['created_at'] ?? $now,
                    "orderNumber" => $emergency['commande'],
                    "carrierTrackingNumber" => $emergency['tracking_nb'],
                    "typeId" => $typeId,
                    "endEmergencyCriteria" => EndEmergencyCriteriaEnum::END_DATE->value,
                    "discr" => EmergencyDiscrEnum::TRACKING_EMERGENCY->value,
                ]
            );

            $this->addSql(
                "
                    INSERT INTO tracking_emergency (
                        id,
                        post_number,
                        internal_article_code,
                        supplier_article_code
                    ) VALUES (
                        :id,
                        :postNumber,
                        :internalArticleCode,
                        :supplierArticleCode
                    )
                ",
                [
                    "id" => $emergency["id"],
                    "postNumber" => $emergency['post_nb'],
                    "internalArticleCode" => $emergency['internal_article_code'],
                    "supplierArticleCode" => $emergency['supplier_article_code'],
                ]
            );

        }
        $urgentArrivals = $this->connection->fetchAllAssociative('
                SELECT urgence.id AS tracking_emergency_id, arrivage.id AS arrival_id
                FROM arrivage
                LEFT JOIN urgence ON arrivage.id = urgence.last_arrival_id
                WHERE is_urgent = 1 AND arrivage.id IS NOT NULL AND urgence.id IS NOT NULL
            ');

        foreach ($urgentArrivals as $urgentArrival) {
            $trackingEmergencyId = $urgentArrival["tracking_emergency_id"];
            $this->addSql(
                "
                    INSERT INTO arrivage_tracking_emergency (
                        tracking_emergency_id,
                        arrivage_id
                    ) VALUES (
                        :trackingEmergencyId,
                        :arrivalId
                    )
                ",
                [
                    "arrivalId" => $urgentArrival['arrival_id'],
                    "trackingEmergencyId" => $trackingEmergencyId
                ]
            );
        }

        $stockEmergencies = $this->connection->fetchAllAssociative('
                SELECT reference_article.id AS ref_art_id,
                       reference_article.emergency_quantity AS quantity,
                       reference_article.emergency_comment AS comment
                FROM reference_article
                WHERE reference_article.is_urgent = 1 AND reference_article.emergency_quantity > 0
            ');

        $this->addSql("INSERT INTO category_type (label) VALUES (:categoryTypeLabel)", [
            'categoryTypeLabel' => CategoryType::STOCK_EMERGENCY,
        ]);

        $this->addSql("
                INSERT INTO type (category_id, color, label, stock_emergency_alert_modes)
                VALUES (
                    (SELECT LAST_INSERT_ID()),
                    :color,
                    :typeLabel,
                    '[]'
                )",
            [
                'color' => Type::DEFAULT_COLOR,
                'typeLabel' => "standard",
            ]
        );

        foreach ($stockEmergencies as $emergency) {
            $this->addSql(
                "
                    INSERT INTO emergency (
                        buyer_id,
                        supplier_id,
                        carrier_id,
                        date_start,
                        date_end,
                        created_at,
                        order_number,
                        carrier_tracking_number,
                        type_id,
                        end_emergency_criteria,
                        discr,
                        comment,
                        closed_at,
                        last_triggered_at,
                        free_fields
                    ) VALUES (
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        :createdAt,
                        NULL,
                        NULL,
                        (
                            SELECT type.id
                            FROM type
                            LEFT JOIN category_type on type.category_id = category_type.id
                            WHERE category_type.label = :categoryTypeLabel AND type.label = :typeLabel
                        ),
                        :endEmergencyCriteria,
                        :discr,
                        :comment,
                        NULL,
                        NULL,
                        NULL
                    )
                ",
                [
                    "createdAt" => $now,
                    "endEmergencyCriteria" => EndEmergencyCriteriaEnum::REMAINING_QUANTITY->value,
                    "discr" => EmergencyDiscrEnum::STOCK_EMERGENCY->value,
                    "comment" => $emergency['comment'],
                    'categoryTypeLabel' => CategoryType::STOCK_EMERGENCY,
                    'typeLabel' => "standard",
                ]
            );

            $this->addSql(
                "
                    INSERT INTO stock_emergency (
                         id,
                         expected_location_id,
                         reference_article_id,
                         emergency_trigger,
                         expected_quantity,
                         already_received_quantity
                    ) VALUES (
                        (SELECT LAST_INSERT_ID()),
                        NULL,
                        :refArtId,
                        :trigger,
                        :expectedquantity,
                        0
                    )
                ",
                [
                    "refArtId" => $emergency['ref_art_id'],
                    "trigger" => EmergencyTriggerEnum::REFERENCE->value,
                    "expectedquantity" => $emergency['quantity'],
                ]
            );
        }

    }

    public function down(Schema $schema): void {}
}
