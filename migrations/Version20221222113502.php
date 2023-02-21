<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221222113502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $users = $this->connection->executeQuery("SELECT id FROM utilisateur")->fetchAllAssociative();

        foreach($users as $user) {
            $defaultVisibleColumns = [
                "dispatch" => Utilisateur::DEFAULT_DISPATCH_VISIBLE_COLUMNS,
                "dispute" => Utilisateur::DEFAULT_DISPUTE_VISIBLE_COLUMNS,
                "arrival" => Utilisateur::DEFAULT_ARRIVAL_VISIBLE_COLUMNS,
                "article" => Utilisateur::DEFAULT_ARTICLE_VISIBLE_COLUMNS,
                "reference" => Utilisateur::DEFAULT_REFERENCE_VISIBLE_COLUMNS,
                "trackingMovement" => Utilisateur::DEFAULT_TRACKING_MOVEMENT_VISIBLE_COLUMNS,
                "reception" => Utilisateur::DEFAULT_RECEPTION_VISIBLE_COLUMNS
            ];

            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = ${user['id']}", ['columns' => json_encode($defaultVisibleColumns)]);
        }
    }
}
