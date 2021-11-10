<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211108131619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $users = $this->connection->executeQuery("SELECT id FROM utilisateur")->fetchAllAssociative();

        foreach ($users as $user) {
            $visibleColumns = $this->connection->executeQuery("SELECT visible_columns FROM utilisateur WHERE id = ${user['id']}")->fetchFirstColumn();
            $visibleColumns = json_decode($visibleColumns[0], true);
            $visibleColumns['deliveryRequest'] = Utilisateur::DEFAULT_DELIVERY_REQUEST_VISIBLE_COLUMNS;
            $this->addSql("UPDATE utilisateur SET visible_columns = :columns WHERE id = ${user['id']}", ['columns' => json_encode($visibleColumns)]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
