<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211108093728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if(!$schema->getTable("utilisateur")->hasColumn("visible_columns")) {
            $this->addSql("ALTER TABLE utilisateur ADD visible_columns JSON DEFAULT NULL");
        }
        $users = $this->connection->executeQuery("SELECT id FROM utilisateur")->fetchAllAssociative();

        foreach ($users as $user) {
            $visibleColumns = [
                "dispatch" => $this->connection->executeQuery("SELECT columns_visible_for_dispatch FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "dispute" => $this->connection->executeQuery("SELECT columns_visible_for_litige FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "arrival" => $this->connection->executeQuery("SELECT columns_visible_for_arrivage FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "article" => $this->connection->executeQuery("SELECT columns_visible_for_article FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "reference" => $this->connection->executeQuery("SELECT column_visible FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "trackingMovement" => $this->connection->executeQuery("SELECT columns_visible_for_tracking_movement FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
                "reception" => $this->connection->executeQuery("SELECT columns_visible_for_reception FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn(),
            ];
            $visibleColumns = array_map(fn($value) => json_decode($value[0]), $visibleColumns);
            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = ${user['id']}", ['columns' => json_encode($visibleColumns)]);
        }

        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_dispatch");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_litige");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_arrivage");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_article");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN column_visible");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_tracking_movement");
        $this->addSql("ALTER TABLE utilisateur DROP COLUMN columns_visible_for_reception");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
