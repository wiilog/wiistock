<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221026090522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        ini_set("memory_limit", "1024M");
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE reception_line (
                id INT AUTO_INCREMENT NOT NULL,
                reception_id INT DEFAULT NULL,
                pack_id INT DEFAULT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
        $this->addSql('ALTER TABLE reception_reference_article ADD reception_line_id INT DEFAULT NULL');

        $receptions = $this->connection
            ->executeQuery("
                SELECT reception.id
                FROM reception WHERE reception.id IN (
                    SELECT DISTINCT reception_reference_article.reception_id
                    FROM reception_reference_article
                )
                ORDER BY reception.id ASC
            ")
            ->iterateColumn();
        foreach ($receptions as $receptionId) {
            $this->addSql("INSERT INTO reception_line(reception_id, pack_id) VALUES (:receptionId, NULL)", [
                'receptionId' => $receptionId
            ]);

            $this->addSql("
                UPDATE reception_reference_article
                SET reception_reference_article.reception_line_id = LAST_INSERT_ID()
                WHERE reception_reference_article.reception_id = :receptionId
            ", [
                'receptionId' => $receptionId,
            ]);
        }

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
