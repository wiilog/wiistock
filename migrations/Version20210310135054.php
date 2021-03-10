<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210310135054 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Trim all pack codes in db';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("UPDATE pack
                            SET pack.code = trim(pack.code)
                            WHERE 1");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
