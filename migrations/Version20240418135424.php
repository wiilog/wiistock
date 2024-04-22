<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240418135424 extends AbstractMigration
{

    public function up(Schema $schema): void
    {

        $users = $this->connection
            ->executeQuery("
            SELECT user.id, user.visible_columns
            FROM utilisateur user
            WHERE user.visible_columns LIKE '%linkedArrival%' OR user.visible_columns NOT LIKE '%onGoing%'
        ")
        ->fetchAllAssociative();
        foreach ($users as $user) {
            $oldVisibleColumns = json_decode($user["visible_columns"] ?: '{}', true);
            if (!isset($oldVisibleColumns["onGoing"])){
                $oldVisibleColumns["onGoing"] = Utilisateur::DEFAULT_ON_GOING_VISIBLE_COLUMNS;
            } else {
                $oldVisibleColumns["onGoing"] = str_replace("linkedArrival", "origin", $oldVisibleColumns["onGoing"]);
            }
            $newVisibleColumns = json_encode($oldVisibleColumns);
            $this->addSql("UPDATE utilisateur user SET visible_columns = :newVisibleColumns WHERE user.id = :userId", [
                "newVisibleColumns" => $newVisibleColumns,
                "userId" => $user["id"]
            ]);
        }
    }

    public function down(Schema $schema): void
    {

    }
}
