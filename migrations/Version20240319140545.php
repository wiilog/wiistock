<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240319140545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $partialStatusState = Statut::PARTIAL;
        if(!$schema->getTable("dispatch")->hasColumn("last_partial_status_date")) {
            $this->addSql('ALTER TABLE dispatch ADD last_partial_status_date DATETIME DEFAULT NULL');
        }
        $this->addSql("UPDATE dispatch SET last_partial_status_date = (
            SELECT status_history.date
            FROM status_history
            INNER JOIN statut ON status_history.status_id = statut.id AND statut.state = :partialStatusState
            WHERE status_history.dispatch_id = dispatch.id
            ORDER BY status_history.date DESC
            LIMIT 1
        ) WHERE last_partial_status_date IS NULL", [
            "partialStatusState" => $partialStatusState,
        ]);

    }

    public function down(Schema $schema): void
    {
    }
}
