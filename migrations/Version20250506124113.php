<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Type\CategoryType;
use App\Entity\Emergency\EmergencyDiscrEnum;
use App\Entity\Emergency\EndEmergencyCriteriaEnum;
use DateTime;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250506124113 extends AbstractMigration {
    public function getDescription(): string {
        return '';
    }

    public function up(Schema $schema): void {
        if ($schema->hasTable('emergency')) {
            return;
        }

        $this->addSql('CREATE TABLE arrivage_tracking_emergency (arrivage_id INT NOT NULL, tracking_emergency_id INT NOT NULL, PRIMARY KEY(arrivage_id, tracking_emergency_id))');
        $this->addSql('CREATE TABLE emergency (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, supplier_id INT DEFAULT NULL, buyer_id INT DEFAULT NULL, carrier_id INT DEFAULT NULL, end_emergency_criteria VARCHAR(255) NOT NULL, comment LONGTEXT DEFAULT NULL, carrier_tracking_number VARCHAR(255) DEFAULT NULL, date_start DATETIME DEFAULT NULL, date_end DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, last_triggered_at DATETIME DEFAULT NULL, order_number VARCHAR(255) DEFAULT NULL, free_fields JSON DEFAULT NULL, discr VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE tracking_emergency (id INT NOT NULL, post_number VARCHAR(255) DEFAULT NULL, internal_article_code VARCHAR(255) DEFAULT NULL, supplier_article_code VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $emergencies = $this->connection->fetchAllAssociative('SELECT * FROM urgence');
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

        foreach ($emergencies as $emergency) {
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
                    "createdAt" => $emergency['created_at'],
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
                WHERE is_urgent = 1
            ');

        $now = (new DateTime("now"))->format('Y-m-d H:i:s');

        $standardEmergencyComment = uniqid();
        $standardEmergencyExist = false;

        foreach ($urgentArrivals as $urgentArrival) {
            if (!$urgentArrival["tracking_emergency_id"] && !$standardEmergencyExist) {
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
                        :dateStart,
                        :dateEnd,
                        :createdAt,
                        NULL,
                        NULL,
                        :typeId,
                        :endEmergencyCriteria,
                        :discr,
                        :comment,
                        NULL,
                        NULL,
                        NULL
                    )
                ",
                    [
                        "dateStart" => $now,
                        "dateEnd" => $now,
                        "createdAt" => $now,
                        "typeId" => $typeIdbyName['standard'],
                        "endEmergencyCriteria" => EndEmergencyCriteriaEnum::END_DATE->value,
                        "discr" => EmergencyDiscrEnum::TRACKING_EMERGENCY->value,
                        "comment" => $standardEmergencyComment,
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
                        (SELECT LAST_INSERT_ID()),
                        NULL,
                        NULL,
                        NULL
                    )
                "
                );
                $standardEmergencyExist = true;
            }

            $trackingEmergencyId = $urgentArrival["tracking_emergency_id"] ? "'".$urgentArrival["tracking_emergency_id"]."'"  : "(SELECT id FROM emergency WHERE comment = '$standardEmergencyComment' ORDER BY id DESC LIMIT 1)";

            $this->addSql(
                "
                        INSERT INTO arrivage_tracking_emergency (
                            tracking_emergency_id,
                            arrivage_id
                        ) VALUES (
                            $trackingEmergencyId,
                            :arrivalId
                        )
                    ",
                [
                    "arrivalId" => $urgentArrival['arrival_id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void {}
}
