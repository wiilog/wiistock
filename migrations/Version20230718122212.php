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
        if(!$schema->hasTable("reserve_type")) {
            $this->addSql("
                CREATE TABLE reserve_type (
                    id INT AUTO_INCREMENT NOT NULL,
                    label VARCHAR(255) DEFAULT NULL,
                    default_reserve_type INT DEFAULT NULL,
                    active INT DEFAULT NULL,
                    PRIMARY KEY (id)
                )
            ");
        }

        $this->addSql("INSERT INTO reserve_type (label, is_default, active) VALUES (:qualitylabel, 1, 1)", [
            "qualitylabel" => ReserveType::DEFAULT_QUALITY_TYPE,
        ]);

        $reserves = $this->connection->executeQuery("
                SELECT reserve.id
                FROM reserve
                WHERE kind = '" . Reserve::KIND_QUALITY . "'"
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
