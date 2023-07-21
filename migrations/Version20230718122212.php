<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Reserve;
use App\Entity\ReserveTypeTOTO;
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
        if (!$schema->hasTable("reserve_type")) {
            $this->addSql('CREATE TABLE reserve_type (id INT NOT NULL, label VARCHAR(255) NOT NULL, default_reserve_type TINYINT(1) DEFAULT NULL, active TINYINT(1) DEFAULT 1)');
            $this->addSql('CREATE TABLE reserve_type_utilisateur (reserve_type_id INT NOT NULL, utilisateur_id INT NOT NULL)');
            $this->addSql('ALTER TABLE reserve ADD reserve_type_id INT NOT NULL');
        }

        $this->addSql("INSERT INTO reserve_type (label, default_reserve_type, active) VALUES (:qualitylabel, 1, 1)", [
            "qualitylabel" => ReserveTypeTOTO::DEFAULT_QUALITY_TYPE,
        ]);

        $reserves = $this->connection->executeQuery("
                SELECT reserve.id
                FROM reserve
                WHERE type = '" . Reserve::KIND_QUALITY . "'"
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
