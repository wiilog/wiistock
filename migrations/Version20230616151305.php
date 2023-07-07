<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Statut;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230616151305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // required step to save old states
        $this->addSql("
            UPDATE statut
            SET statut.state = statut.state * -1
            WHERE statut.state = 5 OR statut.state = 4 OR statut.state = 2 OR statut.state = 3;
        ");
        $this->addSql("UPDATE statut SET statut.state = :state_in_progress WHERE statut.state = -5;", [
            "state_in_progress" => Statut::IN_PROGRESS
        ]);
        $this->addSql("UPDATE statut SET statut.state = :state_partial WHERE statut.state = -4;", [
            "state_partial" => Statut::PARTIAL
        ]);
        $this->addSql("UPDATE statut SET statut.state = :state_treated WHERE statut.state = -2;", [
            "state_treated" => Statut::TREATED
        ]);
        $this->addSql("UPDATE statut SET statut.state = :state_dispute WHERE statut.state = -3;", [
            "state_dispute" => Statut::DISPUTE
        ]);
    }

    public function down(Schema $schema): void
    {
    }
}
