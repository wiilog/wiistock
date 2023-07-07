<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230417134705 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        if ($schema->getTable('type')->hasColumn('send_mail')) {
            $this->addSql('ALTER TABLE type RENAME COLUMN send_mail TO send_mail_requester');
        }
    }

    public function down(Schema $schema): void
    {

    }
}
