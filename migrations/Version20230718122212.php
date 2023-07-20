<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Reserve;
use App\Entity\ReserveType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230718122212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $existingId = $this->connection
            ->executeQuery("
            SELECT reserve_type.id
            FROM reserve_type
            WHERE reserve_type.label = :qualitylabel", [
                "qualitylabel" => ReserveType::DEFAULT_QUALITY_TYPE,
            ])->fetchOne();

        if (!$existingId) {
            $this->addSql("INSERT INTO reserve_type (label, is_default) VALUE (:qualitylabel, true)", [
                "qualitylabel" => ReserveType::DEFAULT_QUALITY_TYPE,
            ]);
            $existingId = $this->connection
                ->executeQuery("
            SELECT reserve_type.id
            FROM reserve_type
            WHERE reserve_type.label = :qualitylabel", [
                    "qualitylabel" => ReserveType::DEFAULT_QUALITY_TYPE,
                ])->fetchOne();
        }

        $reserves = $this->connection->executeQuery("
            SELECT reserve.id
            FROM reserve
            WHERE kind = '" . Reserve::KIND_QUALITY . "'")->fetchAllAssociative();

        foreach ($reserves as $reserve) {
            $this->addSql("
                UPDATE reserve
                SET reserve_type_id = " . $existingId . "
                WHERE id = " . $reserve["id"]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
