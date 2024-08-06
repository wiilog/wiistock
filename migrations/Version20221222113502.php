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
                "dispatch" => Utilisateur::DEFAULT_DISPATCH_FIELDS_MODES,
                "dispute" => Utilisateur::DEFAULT_DISPUTE_FIELDS_MODES,
                "arrival" => Utilisateur::DEFAULT_ARRIVAL_FIELDS_MODES,
                "article" => Utilisateur::DEFAULT_ARTICLE_FIELDS_MODES,
                "reference" => Utilisateur::DEFAULT_REFERENCE_FIELDS_MODES,
                "trackingMovement" => Utilisateur::DEFAULT_TRACKING_MOVEMENT_FIELDS_MODES,
                "reception" => Utilisateur::DEFAULT_RECEPTION_FIELDS_MODES
            ];

            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = {$user['id']}", ['columns' => json_encode($defaultVisibleColumns)]);
        }
    }
}
