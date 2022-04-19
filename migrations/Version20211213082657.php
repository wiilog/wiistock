<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use WiiCommon\Helper\Stream;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211213082657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $query = $this->connection->executeQuery("SELECT id FROM filtre_ref WHERE champ_fixe = 'status'")->fetchAllAssociative();
        $ids = Stream::from($query)->flatten()->toArray();
        foreach ($ids as $id) {
            $this->addSql("UPDATE filtre_ref SET champ_fixe = 'active_only' WHERE id = $id");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
