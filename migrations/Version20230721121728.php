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
final class Version20230721121728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO reserve_type (label, default_reserve_type, active) VALUES (:qualitylabel, 1, 1)", [
            "qualitylabel" => ReserveType::DEFAULT_QUALITY_TYPE,
        ]);

        $reserves = $this->connection->executeQuery("
                SELECT reserve.id
                FROM reserve
                WHERE type = 'quality'"
        )->fetchAllAssociative();

        foreach ($reserves as $reserve) {
            $this->addSql("
                UPDATE reserve
                SET reserve_type_id = (
                    SELECT reserve_type.id
                        FROM reserve_type
                        LIMIT 1
                    )
                WHERE id = {$reserve["id"]}
            ");
        }
        $this->addSql("ALTER TABLE reserve RENAME COLUMN type TO kind");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
