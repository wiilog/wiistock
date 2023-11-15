<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

final class Version20231102092212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transforme la colone pack_code en relation entre receipt_association et pack ';
    }

    public function up(Schema $schema): void
    {
        ini_set("memory_limit", "-1");
        $this->addSql('CREATE TABLE IF NOT EXISTS receipt_association_logistic_unit (receipt_association_id INT NOT NULL, logistic_unit_id INT NOT NULL, INDEX IDX_61DC0EC0C2C6B1E5 (receipt_association_id), INDEX IDX_61DC0EC01919B217 (logistic_unit_id), PRIMARY KEY(receipt_association_id, logistic_unit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $receiptAssociations = $this->connection->fetchAllAssociative('SELECT id, pack_code FROM receipt_association');

        foreach ($receiptAssociations as $receiptAssociation) {
            $logisticUnits = explode(',', $receiptAssociation["pack_code"]);


            foreach ($logisticUnits as $logisticUnit) {
                if(trim($logisticUnit) === '') {
                    continue;
                }

                $logisticUnitId = $this->connection->fetchOne("SELECT id FROM pack WHERE code = :logisticUnitCode", ["logisticUnitCode" => $logisticUnit]);
                if (!$logisticUnitId) {
                    $this->addSql(
                        'INSERT INTO pack (code, delivery_done, article_container) VALUES (:logisticUnitCode, 0, 0)',
                        ["logisticUnitCode" => $logisticUnit]
                    );

                    $this->addSql("INSERT INTO receipt_association_logistic_unit (receipt_association_id, logistic_unit_id) VALUES (:receiptAssociation, (SELECT LAST_INSERT_ID()))",
                        [
                            "receiptAssociation" => $receiptAssociation["id"],
                        ]);
                } else {
                    $this->addSql("INSERT INTO receipt_association_logistic_unit (receipt_association_id, logistic_unit_id) VALUES (:receiptAssociation, :logisticUnitId)",
                        [
                            "receiptAssociation" => $receiptAssociation["id"],
                            "logisticUnitId" => $logisticUnitId,
                        ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
