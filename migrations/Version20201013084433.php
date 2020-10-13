<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201013084433 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this
            ->addSql("ALTER TABLE dispatch_pack ADD treated TINYINT(1) DEFAULT '0' NOT NULL");
        $this
            ->addSql("
                UPDATE dispatch_pack
                INNER JOIN dispatch on dispatch_pack.dispatch_id = dispatch.id
                INNER JOIN statut on dispatch.statut_id = statut.id
                SET treated = 1
                WHERE statut.state = 2
            ");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
