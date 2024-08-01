<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240523115333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void {
        // this up() migration is auto-generated, please modify it to your needs
        $users = $this->connection->iterateAssociative("
            SELECT utilisateur.id AS id,
                   utilisateur.visible_columns AS visible_columns
            FROM utilisateur
            WHERE JSON_SEARCH(JSON_EXTRACT(visible_columns, '$.trackingMovement'), 'one', 'packCode') IS NOT NULL
                OR JSON_SEARCH(JSON_EXTRACT(visible_columns, '$.trackingMovement'), 'one', 'code') IS NOT NULL
        ");

        foreach ($users as $user) {
            $userId = $user["id"];
            $visibleColumnStr = $user["visible_columns"] ?? null;
            $visibleColumn = @json_decode($visibleColumnStr ?: "", true) ?: null;
            if (empty($visibleColumn)) {
                $visibleColumn = Utilisateur::DEFAULT_FIELDS_MODES;
            }
            else {
                $index = array_search("packCode", $visibleColumn["trackingMovement"]);
                if ($index !== false) {
                    $visibleColumn["trackingMovement"][$index] = "pack";
                }

                $index = array_search("code", $visibleColumn["trackingMovement"]);
                if ($index !== false) {
                    $visibleColumn["trackingMovement"][$index] = "pack";
                }
            }

            $this->addSql("
                UPDATE utilisateur
                SET utilisateur.visible_columns = :visible_column
                WHERE utilisateur.id = :user_id
            ", [
                "user_id" => $userId,
                "visible_column" => json_encode($visibleColumn),
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
