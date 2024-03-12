<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240312094058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create and fill dispatch.last_partial_status_date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dispatch ADD last_partial_status_date DATETIME DEFAULT NULL');
        //TODO : WIIS-11128 : WEB - Traçabilité | Acheminements : Migration pour la nouvelle colonne "Date statut partiel"
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dispatch DROP last_partial_status_date');
    }
}
